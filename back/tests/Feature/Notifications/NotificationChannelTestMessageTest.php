<?php

namespace Tests\Feature\Notifications;

use App\Mail\AlertMail;
use App\Models\NotificationChannel;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\FakesDns;
use Tests\Concerns\FakesOutboundHttp;
use Tests\TestCase;

class NotificationChannelTestMessageTest extends TestCase
{
    use FakesDns;
    use FakesOutboundHttp;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeDns(['hooks.example.com' => ['93.184.215.14']]);
    }

    private function sendTest(NotificationChannel $channel)
    {
        return $this->actingAs($channel->user)->fromSpa()->postJson("/api/notification-channels/{$channel->id}/test");
    }

    public function test_telegram_test_message_is_sent(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $channel = NotificationChannel::factory()->telegram()->create();

        $this->sendTest($channel)->assertOk()->assertJsonPath('message', 'Test message sent.');

        Http::assertSent(fn (Request $request) => str_contains($request['text'], 'PulseCheck test message'));
    }

    public function test_webhook_test_payload_has_no_monitor_or_incident(): void
    {
        $this->fakeOutboundHttp(new Response(200));
        $channel = NotificationChannel::factory()->webhook()->create(['config' => ['url' => 'https://hooks.example.com/in', 'headers' => []]]);

        $this->sendTest($channel)->assertOk();

        $payload = $this->outboundRequests[0]['json'];
        $this->assertSame('test', $payload['event']);
        $this->assertSame('info', $payload['severity']);
        $this->assertNull($payload['monitor']);
        $this->assertNull($payload['incident']);
    }

    public function test_email_test_message_is_sent(): void
    {
        Mail::fake();
        $channel = NotificationChannel::factory()->email()->create(['config' => ['email' => 'ops@example.com']]);

        $this->sendTest($channel)->assertOk();

        Mail::assertSent(AlertMail::class, fn (AlertMail $mail) => $mail->hasTo('ops@example.com'));
    }

    public function test_disabled_channels_can_still_be_tested(): void
    {
        Mail::fake();
        $channel = NotificationChannel::factory()->email()->disabled()->create();

        $this->sendTest($channel)->assertOk();

        Mail::assertSent(AlertMail::class);
    }

    public function test_rejection_returns_422_and_outage_returns_503(): void
    {
        $this->fakeOutboundHttp(new Response(401), new Response(502));
        $channel = NotificationChannel::factory()->webhook()->create(['config' => ['url' => 'https://hooks.example.com/in', 'headers' => []]]);

        $this->sendTest($channel)->assertUnprocessable()->assertJsonPath('message', 'The destination rejected the notification. Check the channel settings.');
        $this->sendTest($channel)->assertServiceUnavailable()->assertJsonPath('message', 'The destination could not be reached. Try again later.');
    }

    public function test_test_messages_are_rate_limited_per_user(): void
    {
        Mail::fake();
        RateLimiter::clear('notification-test');
        $channel = NotificationChannel::factory()->email()->create();

        foreach (range(1, 5) as $attempt) {
            $this->sendTest($channel)->assertOk();
        }

        $this->sendTest($channel)->assertTooManyRequests();
    }
}
