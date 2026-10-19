<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationType;
use App\Jobs\SendIncidentNotification;
use App\Models\Incident;
use App\Models\InstanceSettings;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\User;
use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\FakesDns;
use Tests\Concerns\FakesOutboundHttp;
use Tests\TestCase;

class NotificationChannelDeliveryPreferencesTest extends TestCase
{
    use FakesDns;
    use FakesOutboundHttp;
    use RefreshDatabase;

    private const SLACK_URL = 'https://hooks.slack.com/services/T000/B000/SLACKSECRETSLACKSECRET12';

    private const DISCORD_URL = 'https://discord.com/api/webhooks/123456/DISCORDSECRET_discordsecret';

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeDns([
            'hooks.example.com' => ['93.184.215.14'],
            'hooks.slack.com' => ['93.184.215.20'],
            'discord.com' => ['93.184.215.21'],
        ]);
    }

    private function incident(NotificationChannel $channel, string $state = 'open'): Incident
    {
        $monitor = Monitor::factory()->create(['name' => 'Shop', 'url' => 'https://shop.example.com/health']);
        $monitor->notificationChannels()->attach($channel);

        return Incident::factory()->for($monitor)->{$state}()->create(['started_at' => CarbonImmutable::parse('2026-10-17 12:00:00')]);
    }

    private function send(NotificationChannel $channel, Incident $incident, NotificationType $type): void
    {
        Bus::dispatchSync(new SendIncidentNotification($incident->id, $channel->id, $type));
    }

    public function test_channels_created_without_preferences_receive_every_event_in_detail(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->fromSpa()->postJson('/api/notification-channels', [
            'type' => 'email',
            'name' => 'Ops',
            'config' => ['email' => 'ops@example.com'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.events', array_column(NotificationType::cases(), 'value'))
            ->assertJsonPath('data.quiet_hours_start', null)
            ->assertJsonPath('data.quiet_hours_end', null)
            ->assertJsonPath('data.quiet_hours_allow_critical', true)
            ->assertJsonPath('data.message_format', 'detailed');
    }

    public function test_preferences_are_saved_and_returned(): void
    {
        $channel = NotificationChannel::factory()->slack()->create();

        $response = $this->actingAs(User::factory()->admin()->create())->fromSpa()->putJson("/api/notification-channels/{$channel->id}", [
            'events' => ['down', 'recovered'],
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '07:00',
            'quiet_hours_allow_critical' => false,
            'message_format' => 'compact',
            'config' => ['mention' => 'here'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.events', ['down', 'recovered'])
            ->assertJsonPath('data.quiet_hours_start', '22:00')
            ->assertJsonPath('data.quiet_hours_end', '07:00')
            ->assertJsonPath('data.quiet_hours_allow_critical', false)
            ->assertJsonPath('data.message_format', 'compact')
            ->assertJsonPath('data.config.mention', 'here')
            ->assertJsonPath('data.secrets_set', ['webhook_url']);
    }

    public static function invalidPreferences(): array
    {
        return [
            'no events' => [['events' => []], 'events'],
            'unknown event' => [['events' => ['exploded']], 'events.0'],
            'start without end' => [['quiet_hours_start' => '22:00'], 'quiet_hours_end'],
            'same start and end' => [['quiet_hours_start' => '22:00', 'quiet_hours_end' => '22:00'], 'quiet_hours_start'],
            'malformed time' => [['quiet_hours_start' => '25:00', 'quiet_hours_end' => '07:00'], 'quiet_hours_start'],
            'unknown format' => [['message_format' => 'huge'], 'message_format'],
        ];
    }

    #[DataProvider('invalidPreferences')]
    public function test_invalid_preferences_are_rejected(array $payload, string $field): void
    {
        $channel = NotificationChannel::factory()->email()->create();

        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->putJson("/api/notification-channels/{$channel->id}", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    public function test_invalid_driver_options_are_rejected(): void
    {
        $user = User::factory()->admin()->create();
        $slack = NotificationChannel::factory()->slack()->create();
        $discord = NotificationChannel::factory()->discord()->create();

        $this->actingAs($user)->fromSpa()->putJson("/api/notification-channels/{$slack->id}", ['config' => ['mention' => 'everyone']])
            ->assertJsonValidationErrors('config.mention');
        $this->actingAs($user)->fromSpa()->putJson("/api/notification-channels/{$discord->id}", ['config' => ['mention_role_id' => '@ops']])
            ->assertJsonValidationErrors('config.mention_role_id');
    }

    public function test_unsubscribed_events_are_not_sent(): void
    {
        $this->fakeOutboundHttp(new Response(200));
        $channel = NotificationChannel::factory()->webhook()->create(['events' => ['down']]);
        $incident = $this->incident($channel, 'resolved');

        $this->send($channel, $incident, NotificationType::Recovered);

        $this->assertCount(0, $this->outboundRequests);
    }

    public function test_quiet_hours_spanning_midnight_hold_back_non_critical_alerts(): void
    {
        $this->fakeOutboundHttp(new Response(200), new Response(200));
        InstanceSettings::factory()->create(['timezone' => 'America/Sao_Paulo']);
        $channel = NotificationChannel::factory()->webhook()->create(['quiet_hours_start' => '22:00', 'quiet_hours_end' => '07:00']);
        $incident = $this->incident($channel, 'resolved');

        $this->travelTo(CarbonImmutable::parse('2026-10-18 05:30:00', 'UTC'));
        $this->send($channel, $incident, NotificationType::Recovered);
        $this->assertCount(0, $this->outboundRequests);

        $this->travelTo(CarbonImmutable::parse('2026-10-18 10:30:00', 'UTC'));
        $this->send($channel, $incident, NotificationType::Recovered);
        $this->assertCount(1, $this->outboundRequests);
    }

    public function test_quiet_hours_let_critical_alerts_through_only_when_allowed(): void
    {
        $this->fakeOutboundHttp(new Response(200));
        $this->travelTo(CarbonImmutable::parse('2026-10-18 13:00:00', 'UTC'));
        $allowing = NotificationChannel::factory()->webhook()->create(['quiet_hours_start' => '12:00', 'quiet_hours_end' => '14:00']);
        $blocking = NotificationChannel::factory()->webhook()->create(['quiet_hours_start' => '12:00', 'quiet_hours_end' => '14:00', 'quiet_hours_allow_critical' => false]);

        $this->send($allowing, $this->incident($allowing), NotificationType::Down);
        $this->send($blocking, $this->incident($blocking), NotificationType::Down);

        $this->assertCount(1, $this->outboundRequests);
    }

    public function test_compact_format_drops_the_detail_fields(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $channel = NotificationChannel::factory()->telegram()->create(['message_format' => 'compact']);

        $this->send($channel, $this->incident($channel), NotificationType::Down);

        Http::assertSent(fn (Request $request) => $request['text'] === '🔴 Shop is DOWN');
    }

    public function test_telegram_silent_option_disables_the_notification_sound(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $channel = NotificationChannel::factory()->telegram()->create();
        $channel->update(['config' => [...$channel->config, 'silent' => true]]);

        $this->send($channel, $this->incident($channel), NotificationType::Down);

        Http::assertSent(fn (Request $request) => $request['disable_notification'] === 'true');
    }

    public function test_slack_mention_is_added_to_critical_alerts_only(): void
    {
        $this->fakeOutboundHttp(new Response(200), new Response(200));
        $channel = NotificationChannel::factory()->slack()->create(['config' => ['webhook_url' => self::SLACK_URL, 'mention' => 'channel']]);

        $this->send($channel, $this->incident($channel), NotificationType::Down);
        $this->send($channel, $this->incident($channel, 'resolved'), NotificationType::Recovered);

        $down = $this->outboundRequests[0]['json'];
        $this->assertStringStartsWith('<!channel> ', $down['text']);
        $this->assertSame(['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => '<!channel>']], $down['blocks'][1]);
        $this->assertStringNotContainsString('<!channel>', json_encode($this->outboundRequests[1]['json']));
    }

    public function test_discord_role_mention_is_allowed_only_for_that_role(): void
    {
        $this->fakeOutboundHttp(new Response(204));
        $channel = NotificationChannel::factory()->discord()->create(['config' => ['webhook_url' => self::DISCORD_URL, 'mention_role_id' => '123456789012345678']]);

        $this->send($channel, $this->incident($channel), NotificationType::Down);

        $payload = $this->outboundRequests[0]['json'];
        $this->assertSame('<@&123456789012345678>', $payload['content']);
        $this->assertSame(['parse' => [], 'roles' => ['123456789012345678']], $payload['allowed_mentions']);
    }

    public function test_preview_renders_a_sample_alert(): void
    {
        $user = User::factory()->admin()->create();

        $detailed = $this->actingAs($user)->fromSpa()->getJson('/api/notification-channels/preview?event=down&message_format=detailed');
        $compact = $this->actingAs($user)->fromSpa()->getJson('/api/notification-channels/preview?event=recovered&message_format=compact');

        $detailed->assertOk()
            ->assertJsonPath('data.severity', 'critical')
            ->assertJsonPath('data.title', '🔴 Example API is DOWN')
            ->assertJsonPath('data.fields.0', ['label' => 'Host', 'value' => 'api.example.com'])
            ->assertJsonPath('data.fields.1', ['label' => 'Cause', 'value' => 'HTTP 503']);
        $compact->assertOk()
            ->assertJsonPath('data.severity', 'resolved')
            ->assertJsonPath('data.fields', []);
    }

    public function test_preview_requires_permission_to_manage_resources(): void
    {
        $this->actingAs(User::factory()->create())->fromSpa()
            ->getJson('/api/notification-channels/preview?event=down&message_format=detailed')
            ->assertForbidden();
    }
}
