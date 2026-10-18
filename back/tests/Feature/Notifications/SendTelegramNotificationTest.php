<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationType;
use App\Jobs\RunMonitorCheck;
use App\Jobs\SendTelegramNotification;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationSetting;
use App\Security\SafeHttpClient;
use App\Services\TelegramClient;
use Carbon\CarbonImmutable;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesDns;
use Tests\TestCase;

class SendTelegramNotificationTest extends TestCase
{
    use FakesDns;
    use RefreshDatabase;

    private const TOKEN = '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw_';

    private array $logged = [];

    protected function setUp(): void
    {
        parent::setUp();

        Event::listen(MessageLogged::class, function (MessageLogged $log): void {
            $this->logged[] = $log->message.' '.json_encode($log->context);
        });
    }

    private function monitor(array $attributes = []): Monitor
    {
        $setting = NotificationSetting::factory()->create([
            'telegram_bot_token' => self::TOKEN,
            'telegram_chat_id' => '-100123',
            'telegram_enabled' => true,
        ]);

        return Monitor::factory()->for($setting->user)->create(['name' => 'Shop API', 'url' => 'https://shop.example.com:8443/health?key=secret', ...$attributes]);
    }

    private function assertTokenNeverLogged(): void
    {
        foreach ($this->logged as $entry) {
            $this->assertStringNotContainsString(self::TOKEN, $entry);
            $this->assertStringNotContainsString('AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw_', $entry);
        }
    }

    public function test_down_message_contains_name_host_cause_and_time_without_query_string(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $incident = Incident::factory()->for($this->monitor())->open()->create([
            'started_at' => CarbonImmutable::parse('2026-10-17 12:00:00'),
            'cause' => 'timeout: Operation timed out',
        ]);

        Bus::dispatchSync(new SendTelegramNotification($incident->id, NotificationType::Down));

        Http::assertSent(function (Request $request) {
            $text = $request['text'];

            return $request['chat_id'] === '-100123'
                && ! isset($request['parse_mode'])
                && str_contains($text, 'Shop API is DOWN')
                && str_contains($text, 'shop.example.com:8443')
                && ! str_contains($text, 'key=secret')
                && str_contains($text, 'timeout: Operation timed out')
                && str_contains($text, '2026-10-17 12:00:00 UTC');
        });
    }

    public function test_recovery_message_contains_the_downtime(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $incident = Incident::factory()->for($this->monitor())->create([
            'started_at' => CarbonImmutable::parse('2026-10-17 12:00:00'),
            'resolved_at' => CarbonImmutable::parse('2026-10-17 13:05:30'),
        ]);

        Bus::dispatchSync(new SendTelegramNotification($incident->id, NotificationType::Recovered));

        Http::assertSent(fn (Request $request) => str_contains($request['text'], 'Shop API is back UP')
            && str_contains($request['text'], 'Downtime: 1h 5m 30s'));
    }

    public function test_nothing_is_sent_when_telegram_was_disabled_meanwhile(): void
    {
        Http::fake();
        $incident = Incident::factory()->for($this->monitor())->open()->create();
        $incident->monitor->user->notificationSetting->update(['telegram_enabled' => false]);

        Bus::dispatchSync(new SendTelegramNotification($incident->id, NotificationType::Down));

        Http::assertNothingSent();
    }

    public function test_down_alert_is_not_sent_when_the_incident_is_already_resolved(): void
    {
        Http::fake();
        $incident = Incident::factory()->for($this->monitor())->resolved()->create();

        Bus::dispatchSync(new SendTelegramNotification($incident->id, NotificationType::Down));

        Http::assertNothingSent();
    }

    public function test_missing_incident_is_ignored(): void
    {
        Http::fake();

        Bus::dispatchSync(new SendTelegramNotification(999999, NotificationType::Down));

        Http::assertNothingSent();
    }

    private function fakeJob(Incident $incident, int $attempt): SendTelegramNotification
    {
        $job = (new SendTelegramNotification($incident->id, NotificationType::Down))->withFakeQueueInteractions();
        $job->job->attempts = $attempt;

        return $job;
    }

    public function test_server_errors_are_released_with_backoff_until_the_last_attempt(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Internal Server Error'], 500)]);
        $incident = Incident::factory()->for($this->monitor())->open()->create();

        $firstAttempt = $this->fakeJob($incident, 1);
        $firstAttempt->handle(app(TelegramClient::class));
        $firstAttempt->assertReleased($firstAttempt->backoff()[0]);

        $lastAttempt = $this->fakeJob($incident, 3);
        $lastAttempt->handle(app(TelegramClient::class));
        $lastAttempt->assertNotReleased();
        $lastAttempt->assertNotFailed();

        $this->assertSame(3, $lastAttempt->tries);
        $this->assertNotEmpty($this->logged);
        $this->assertTokenNeverLogged();
    }

    public function test_rejected_credentials_are_not_retried(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Forbidden: bot was blocked by the user'], 403)]);
        $incident = Incident::factory()->for($this->monitor())->open()->create();

        $job = $this->fakeJob($incident, 1);
        $job->handle(app(TelegramClient::class));

        $job->assertNotReleased();
        $job->assertNotFailed();
        $this->assertTokenNeverLogged();
    }

    public function test_connection_failures_never_log_the_token(): void
    {
        Http::fake(['api.telegram.org/*' => Http::failedConnection()]);
        $incident = Incident::factory()->for($this->monitor())->open()->create();

        $job = $this->fakeJob($incident, 3);
        $job->handle(app(TelegramClient::class));

        $this->assertNotEmpty($this->logged);
        $this->assertTokenNeverLogged();
        $job->assertNotFailed();
    }

    public function test_telegram_outage_does_not_break_the_check_flow(): void
    {
        Http::fake(['api.telegram.org/*' => Http::failedConnection()]);
        $this->fakeDns(['shop.example.com' => ['93.184.215.14']]);
        $handler = new MockHandler([new Response(503), new Response(503), new Response(503)]);
        $this->app->instance(SafeHttpClient::class, $this->app->make(SafeHttpClient::class, ['handler' => $handler]));
        $monitor = $this->monitor(['status' => 'up']);

        foreach (range(1, 3) as $attempt) {
            Bus::dispatchSync(new RunMonitorCheck($monitor));
        }

        $this->assertSame(3, $monitor->checkResults()->count());
        $this->assertSame(1, $monitor->incidents()->whereNull('resolved_at')->count());
        $this->assertTokenNeverLogged();
    }
}
