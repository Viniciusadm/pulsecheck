<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationType;
use App\Jobs\RunMonitorCheck;
use App\Jobs\SendIncidentNotification;
use App\Mail\AlertMail;
use App\Models\Incident;
use App\Models\InstanceSettings;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Notifications\AlertMessage;
use Carbon\CarbonImmutable;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\FakesDns;
use Tests\Concerns\FakesOutboundHttp;
use Tests\TestCase;

class SendIncidentNotificationTest extends TestCase
{
    use FakesDns;
    use FakesOutboundHttp;
    use RefreshDatabase;

    private const TOKEN = '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw_';

    private const SLACK_URL = 'https://hooks.slack.com/services/T000/B000/SLACKSECRETSLACKSECRET12';

    private const DISCORD_URL = 'https://discord.com/api/webhooks/123456/DISCORDSECRET_discordsecret';

    private array $logged = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeDns([
            'hooks.example.com' => ['93.184.215.14'],
            'hooks.slack.com' => ['93.184.215.20'],
            'discord.com' => ['93.184.215.21'],
            'internal.example.com' => ['10.0.0.5'],
            'shop.example.com' => ['93.184.215.30'],
        ]);

        Event::listen(MessageLogged::class, function (MessageLogged $log): void {
            $this->logged[] = $log->message.' '.json_encode($log->context);
        });
    }

    private function incident(NotificationChannel $channel, string $state = 'open', array $attributes = []): Incident
    {
        $monitor = Monitor::factory()->create(['name' => 'Shop <API>', 'url' => 'https://shop.example.com:8443/health?key=secret']);
        $monitor->notificationChannels()->attach($channel);

        return Incident::factory()->for($monitor)->{$state}()->create([
            'started_at' => CarbonImmutable::parse('2026-10-17 12:00:00'),
            'cause' => 'timeout: Operation timed out',
            ...$attributes,
        ]);
    }

    private function assertSecretsNeverLogged(string ...$secrets): void
    {
        foreach ($this->logged as $entry) {
            foreach ($secrets as $secret) {
                $this->assertStringNotContainsString($secret, $entry);
            }
        }
    }

    private function fakeJob(Incident $incident, NotificationChannel $channel, int $attempt): SendIncidentNotification
    {
        $job = (new SendIncidentNotification($incident->id, $channel->id, NotificationType::Down))->withFakeQueueInteractions();
        $job->job->attempts = $attempt;

        return $job;
    }

    public function test_telegram_down_message_contains_name_host_cause_and_time_without_query_string(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $channel = NotificationChannel::factory()->telegram()->create(['config' => ['bot_token' => self::TOKEN, 'chat_id' => '-100123']]);
        $incident = $this->incident($channel);

        Bus::dispatchSync(new SendIncidentNotification($incident->id, $channel->id, NotificationType::Down));

        Http::assertSent(function (Request $request) {
            $text = $request['text'];

            return str_contains($request->url(), '/bot'.self::TOKEN.'/sendMessage')
                && $request['chat_id'] === '-100123'
                && ! isset($request['parse_mode'])
                && str_contains($text, 'Shop <API> is DOWN')
                && str_contains($text, 'Host: shop.example.com:8443')
                && ! str_contains($text, 'key=secret')
                && str_contains($text, 'Cause: timeout: Operation timed out')
                && str_contains($text, 'Since: 2026-10-17 12:00:00 UTC');
        });
    }

    public function test_telegram_recovery_message_contains_the_downtime(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $channel = NotificationChannel::factory()->telegram()->create();
        $incident = $this->incident($channel, 'resolved', ['resolved_at' => CarbonImmutable::parse('2026-10-17 13:05:30')]);

        Bus::dispatchSync(new SendIncidentNotification($incident->id, $channel->id, NotificationType::Recovered));

        Http::assertSent(fn (Request $request) => str_contains($request['text'], 'Shop <API> is back UP')
            && str_contains($request['text'], 'Downtime: 1h 5m 30s'));
    }

    public function test_webhook_receives_the_versioned_payload_and_custom_headers(): void
    {
        $this->fakeOutboundHttp(new Response(204));
        $channel = NotificationChannel::factory()->webhook()->create(['config' => [
            'url' => 'https://hooks.example.com/pulse',
            'headers' => ['Authorization' => 'Bearer s3cret-token'],
        ]]);
        $incident = $this->incident($channel);

        Bus::dispatchSync(new SendIncidentNotification($incident->id, $channel->id, NotificationType::Down));

        $this->assertCount(1, $this->outboundRequests);
        $request = $this->outboundRequests[0];
        $this->assertSame('https://hooks.example.com/pulse', $request['url']);
        $this->assertSame('Bearer s3cret-token', $request['headers']['Authorization']);
        $this->assertSame([
            'version' => 1,
            'event' => 'monitor.down',
            'severity' => 'critical',
            'title' => '🔴 Shop <API> is DOWN',
            'text' => "🔴 Shop <API> is DOWN\nHost: shop.example.com:8443\nCause: timeout: Operation timed out\nSince: 2026-10-17 12:00:00 UTC",
            'occurred_at' => '2026-10-17T12:00:00Z',
            'instance' => ['name' => 'PulseCheck'],
            'monitor' => ['id' => $incident->monitor_id, 'name' => 'Shop <API>', 'host' => 'shop.example.com:8443'],
            'incident' => [
                'id' => $incident->id,
                'cause' => 'timeout: Operation timed out',
                'started_at' => '2026-10-17T12:00:00Z',
                'resolved_at' => null,
                'duration_seconds' => null,
            ],
        ], $request['json']);
    }

    public function test_slack_receives_escaped_blocks(): void
    {
        $this->fakeOutboundHttp(new Response(200));
        $channel = NotificationChannel::factory()->slack()->create(['config' => ['webhook_url' => self::SLACK_URL]]);
        $incident = $this->incident($channel);

        Bus::dispatchSync(new SendIncidentNotification($incident->id, $channel->id, NotificationType::Down));

        $request = $this->outboundRequests[0];
        $this->assertSame(self::SLACK_URL, $request['url']);
        $this->assertStringContainsString('Shop &lt;API&gt; is DOWN', $request['json']['text']);
        $this->assertSame('header', $request['json']['blocks'][0]['type']);
        $this->assertSame('🔴 Shop <API> is DOWN', $request['json']['blocks'][0]['text']['text']);
        $this->assertSame("*Host*\nshop.example.com:8443", $request['json']['blocks'][1]['fields'][0]['text']);
    }

    public function test_discord_receives_a_colored_embed_without_mentions(): void
    {
        $this->fakeOutboundHttp(new Response(204));
        $channel = NotificationChannel::factory()->discord()->create(['config' => ['webhook_url' => self::DISCORD_URL]]);
        $incident = $this->incident($channel, 'resolved', ['resolved_at' => CarbonImmutable::parse('2026-10-17 12:00:45')]);

        Bus::dispatchSync(new SendIncidentNotification($incident->id, $channel->id, NotificationType::Recovered));

        $payload = $this->outboundRequests[0]['json'];
        $this->assertSame(['parse' => []], $payload['allowed_mentions']);
        $this->assertSame('🟢 Shop <API> is back UP', $payload['embeds'][0]['title']);
        $this->assertSame(0x16A34A, $payload['embeds'][0]['color']);
        $this->assertSame('2026-10-17T12:00:45Z', $payload['embeds'][0]['timestamp']);
        $this->assertSame(['name' => 'Downtime', 'value' => '45s', 'inline' => false], $payload['embeds'][0]['fields'][1]);
    }

    public function test_email_is_sent_to_the_configured_address(): void
    {
        Mail::fake();
        $channel = NotificationChannel::factory()->email()->create(['config' => ['email' => 'ops@example.com']]);
        $incident = $this->incident($channel);

        Bus::dispatchSync(new SendIncidentNotification($incident->id, $channel->id, NotificationType::Down));

        Mail::assertSent(AlertMail::class, fn (AlertMail $mail) => $mail->hasTo('ops@example.com')
            && $mail->alert->title === '🔴 Shop <API> is DOWN');
    }

    public function test_email_body_renders_the_alert_fields(): void
    {
        $channel = NotificationChannel::factory()->email()->create();
        $incident = $this->incident($channel);

        $mail = new AlertMail(AlertMessage::fromIncident($incident->load('monitor'), NotificationType::Down));

        $mail->assertHasSubject('[PulseCheck] 🔴 Shop <API> is DOWN');
        $mail->assertSeeInHtml('shop.example.com:8443');
        $mail->assertSeeInHtml('timeout: Operation timed out');
        $mail->assertDontSeeInHtml('key=secret');
    }

    public function test_alerts_use_the_instance_name_and_timezone(): void
    {
        InstanceSettings::factory()->create(['name' => 'Acme Ops', 'timezone' => 'America/New_York']);
        $channel = NotificationChannel::factory()->email()->create();
        $incident = $this->incident($channel)->load('monitor');

        $alert = AlertMessage::fromIncident($incident, NotificationType::Down);
        $mail = new AlertMail($alert);

        $this->assertStringContainsString('Since: 2026-10-17 08:00:00 EDT', $alert->toText());
        $this->assertSame('2026-10-17T12:00:00Z', $alert->toPayload()['occurred_at']);
        $this->assertSame(['name' => 'Acme Ops'], $alert->toPayload()['instance']);
        $mail->assertHasSubject('[Acme Ops] 🔴 Shop <API> is DOWN');
        $mail->assertSeeInHtml('Acme Ops');
    }

    public function test_test_message_mentions_the_instance_name(): void
    {
        InstanceSettings::factory()->create(['name' => 'Acme Ops']);

        $this->assertSame('✅ Acme Ops test message', AlertMessage::test()->title);
    }

    public function test_nothing_is_sent_when_the_channel_was_disabled_detached_or_deleted_meanwhile(): void
    {
        $this->fakeOutboundHttp();
        $disabled = NotificationChannel::factory()->create();
        $detached = NotificationChannel::factory()->create();
        $deleted = NotificationChannel::factory()->create();
        $disabledIncident = $this->incident($disabled);
        $detachedIncident = $this->incident($detached);
        $deletedIncident = $this->incident($deleted);
        $disabled->update(['enabled' => false]);
        $detachedIncident->monitor->notificationChannels()->detach();
        $deleted->delete();

        Bus::dispatchSync(new SendIncidentNotification($disabledIncident->id, $disabled->id, NotificationType::Down));
        Bus::dispatchSync(new SendIncidentNotification($detachedIncident->id, $detached->id, NotificationType::Down));
        Bus::dispatchSync(new SendIncidentNotification($deletedIncident->id, $deleted->id, NotificationType::Down));
        Bus::dispatchSync(new SendIncidentNotification(999999, $disabled->id, NotificationType::Down));

        $this->assertSame([], $this->outboundRequests);
    }

    public function test_a_channel_not_attached_to_the_monitor_is_never_used(): void
    {
        $this->fakeOutboundHttp();
        $incident = $this->incident(NotificationChannel::factory()->create());
        $foreign = NotificationChannel::factory()->create();

        Bus::dispatchSync(new SendIncidentNotification($incident->id, $foreign->id, NotificationType::Down));

        $this->assertSame([], $this->outboundRequests);
    }

    public function test_down_alert_is_not_sent_when_the_incident_is_already_resolved(): void
    {
        $this->fakeOutboundHttp();
        $channel = NotificationChannel::factory()->create();
        $incident = $this->incident($channel, 'resolved');

        Bus::dispatchSync(new SendIncidentNotification($incident->id, $channel->id, NotificationType::Down));

        $this->assertSame([], $this->outboundRequests);
    }

    public function test_server_errors_are_released_with_backoff_until_the_last_attempt(): void
    {
        $this->fakeOutboundHttp(new Response(500), new Response(503));
        $channel = NotificationChannel::factory()->slack()->create(['config' => ['webhook_url' => self::SLACK_URL]]);
        $incident = $this->incident($channel);

        $firstAttempt = $this->fakeJob($incident, $channel, 1);
        $firstAttempt->handle();
        $firstAttempt->assertReleased($firstAttempt->backoff()[0]);

        $lastAttempt = $this->fakeJob($incident, $channel, 3);
        $lastAttempt->handle();
        $lastAttempt->assertNotReleased();
        $lastAttempt->assertNotFailed();

        $this->assertNotEmpty($this->logged);
        $this->assertSecretsNeverLogged(self::SLACK_URL, 'SLACKSECRET');
    }

    public function test_rejections_are_not_retried(): void
    {
        $this->fakeOutboundHttp(new Response(404));
        $channel = NotificationChannel::factory()->discord()->create(['config' => ['webhook_url' => self::DISCORD_URL]]);
        $incident = $this->incident($channel);

        $job = $this->fakeJob($incident, $channel, 1);
        $job->handle();

        $job->assertNotReleased();
        $job->assertNotFailed();
        $this->assertSecretsNeverLogged('DISCORDSECRET');
    }

    public function test_connection_failures_are_retried_and_never_log_secrets(): void
    {
        $this->fakeOutboundHttp(new ConnectException('Connection refused for https://hooks.example.com/pulse', new PsrRequest('POST', 'https://hooks.example.com/pulse')));
        $channel = NotificationChannel::factory()->webhook()->create(['config' => [
            'url' => 'https://hooks.example.com/pulse',
            'headers' => ['Authorization' => 'Bearer s3cret-token'],
        ]]);
        $incident = $this->incident($channel);

        $job = $this->fakeJob($incident, $channel, 1);
        $job->handle();

        $job->assertReleased(30);
        $this->assertSecretsNeverLogged('s3cret-token', 'hooks.example.com/pulse');
    }

    public function test_webhook_pointing_to_a_private_address_is_blocked_and_not_retried(): void
    {
        $this->fakeOutboundHttp();
        $channel = NotificationChannel::factory()->webhook()->create(['config' => ['url' => 'https://internal.example.com/hook']]);
        $incident = $this->incident($channel);

        $job = $this->fakeJob($incident, $channel, 1);
        $job->handle();

        $job->assertNotReleased();
        $this->assertSame([], $this->outboundRequests);
    }

    public function test_telegram_rejection_never_logs_the_token(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Forbidden: bot was blocked by the user'], 403)]);
        $channel = NotificationChannel::factory()->telegram()->create(['config' => ['bot_token' => self::TOKEN, 'chat_id' => '1']]);
        $incident = $this->incident($channel);

        $job = $this->fakeJob($incident, $channel, 1);
        $job->handle();

        $job->assertNotReleased();
        $this->assertNotEmpty($this->logged);
        $this->assertSecretsNeverLogged(self::TOKEN, 'AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw_');
    }

    public function test_delivery_outage_does_not_break_the_check_flow(): void
    {
        Http::fake(['api.telegram.org/*' => Http::failedConnection()]);
        $channel = NotificationChannel::factory()->telegram()->create(['config' => ['bot_token' => self::TOKEN, 'chat_id' => '1']]);
        $monitor = Monitor::factory()->up()->create(['url' => 'https://shop.example.com/']);
        $monitor->notificationChannels()->attach($channel);
        $this->fakeOutboundHttp(new Response(503), new Response(503), new Response(503));

        foreach (range(1, 3) as $attempt) {
            Bus::dispatchSync(new RunMonitorCheck($monitor));
        }

        $this->assertSame(3, $monitor->checkResults()->count());
        $this->assertSame(1, $monitor->incidents()->whereNull('resolved_at')->count());
        $this->assertSecretsNeverLogged(self::TOKEN);
    }
}
