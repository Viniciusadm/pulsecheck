<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationType;
use App\Jobs\SendIncidentNotification;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\User;
use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\FakesDns;
use Tests\Concerns\FakesOutboundHttp;
use Tests\TestCase;

class AdditionalChannelDriversTest extends TestCase
{
    use FakesDns;
    use FakesOutboundHttp;
    use RefreshDatabase;

    private const TEAMS_URL = 'https://prod-01.westus.logic.azure.com:443/workflows/abc123/triggers/manual/paths/invoke?api-version=2016-06-01&sig=SECRETSIGNATURE';

    private const CHAT_URL = 'https://chat.googleapis.com/v1/spaces/AAAAexample/messages?key=KEYSECRET&token=TOKENSECRET';

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeDns([
            'prod-01.westus.logic.azure.com' => ['93.184.215.40'],
            'chat.googleapis.com' => ['93.184.215.41'],
            'ntfy.sh' => ['93.184.215.42'],
            'ntfy.example.com' => ['93.184.215.43'],
            'api.pushover.net' => ['93.184.215.44'],
            'events.pagerduty.com' => ['93.184.215.45'],
            'shop.example.com' => ['93.184.215.30'],
        ]);
    }

    private function incident(NotificationChannel $channel, string $state = 'open', array $attributes = []): Incident
    {
        $monitor = Monitor::factory()->create(['name' => 'Shop API', 'url' => 'https://shop.example.com/health']);
        $monitor->notificationChannels()->attach($channel);

        return Incident::factory()->for($monitor)->{$state}()->create([
            'started_at' => CarbonImmutable::parse('2026-10-17 12:00:00'),
            'cause' => 'timeout: Operation timed out',
            ...$attributes,
        ]);
    }

    public function test_microsoft_teams_receives_an_adaptive_card(): void
    {
        $this->fakeOutboundHttp(new Response(202));
        $channel = NotificationChannel::factory()->microsoftTeams()->create(['config' => ['webhook_url' => self::TEAMS_URL]]);

        Bus::dispatchSync(new SendIncidentNotification($this->incident($channel)->id, $channel->id, NotificationType::Down));

        $request = $this->outboundRequests[0];
        $this->assertSame(str_replace(':443', '', self::TEAMS_URL), $request['url']);
        $card = $request['json']['attachments'][0]['content'];
        $this->assertSame('application/vnd.microsoft.card.adaptive', $request['json']['attachments'][0]['contentType']);
        $this->assertSame('🔴 Shop API is DOWN', $card['body'][0]['text']);
        $this->assertSame('Attention', $card['body'][0]['color']);
        $this->assertSame(['title' => 'Host', 'value' => 'shop.example.com'], $card['body'][1]['facts'][0]);
    }

    public function test_google_chat_receives_formatted_text(): void
    {
        $this->fakeOutboundHttp(new Response(200));
        $channel = NotificationChannel::factory()->googleChat()->create(['config' => ['webhook_url' => self::CHAT_URL]]);

        Bus::dispatchSync(new SendIncidentNotification($this->incident($channel)->id, $channel->id, NotificationType::Down));

        $text = $this->outboundRequests[0]['json']['text'];
        $this->assertStringStartsWith("*🔴 Shop API is DOWN*\n*Host:* shop.example.com", $text);
    }

    public function test_ntfy_publishes_with_priority_and_optional_token(): void
    {
        $this->fakeOutboundHttp(new Response(200), new Response(200));
        $public = NotificationChannel::factory()->ntfy()->create(['config' => ['topic' => 'acme-alerts']]);
        $private = NotificationChannel::factory()->ntfy()->create(['config' => ['topic' => 'ops', 'server_url' => 'https://ntfy.example.com/', 'access_token' => 'tk_secret']]);

        Bus::dispatchSync(new SendIncidentNotification($this->incident($public)->id, $public->id, NotificationType::Down));
        Bus::dispatchSync(new SendIncidentNotification($this->incident($private, 'resolved', ['resolved_at' => CarbonImmutable::parse('2026-10-17 12:05:00')])->id, $private->id, NotificationType::Recovered));

        $this->assertSame('https://ntfy.sh', rtrim($this->outboundRequests[0]['url'], '/'));
        $this->assertSame('acme-alerts', $this->outboundRequests[0]['json']['topic']);
        $this->assertSame(5, $this->outboundRequests[0]['json']['priority']);
        $this->assertStringStartsWith('Host: shop.example.com', $this->outboundRequests[0]['json']['message']);
        $this->assertArrayNotHasKey('Authorization', $this->outboundRequests[0]['headers']);

        $this->assertSame('https://ntfy.example.com', rtrim($this->outboundRequests[1]['url'], '/'));
        $this->assertSame(3, $this->outboundRequests[1]['json']['priority']);
        $this->assertSame('Bearer tk_secret', $this->outboundRequests[1]['headers']['Authorization']);
    }

    public function test_pushover_sends_high_priority_for_outages(): void
    {
        $this->fakeOutboundHttp(new Response(200));
        $channel = NotificationChannel::factory()->pushover()->create(['config' => ['app_token' => str_repeat('a', 30), 'user_key' => str_repeat('u', 30), 'device' => 'phone']]);

        Bus::dispatchSync(new SendIncidentNotification($this->incident($channel)->id, $channel->id, NotificationType::Down));

        $payload = $this->outboundRequests[0]['json'];
        $this->assertSame('https://api.pushover.net/1/messages.json', $this->outboundRequests[0]['url']);
        $this->assertSame([str_repeat('a', 30), str_repeat('u', 30), 1, 'phone'], [$payload['token'], $payload['user'], $payload['priority'], $payload['device']]);
        $this->assertSame(CarbonImmutable::parse('2026-10-17 12:00:00')->getTimestamp(), $payload['timestamp']);
    }

    public function test_pagerduty_triggers_and_resolves_with_the_same_dedup_key(): void
    {
        $this->fakeOutboundHttp(new Response(202), new Response(202));
        $channel = NotificationChannel::factory()->pagerDuty()->create(['config' => ['routing_key' => str_repeat('r', 32)]]);
        $incident = $this->incident($channel);

        Bus::dispatchSync(new SendIncidentNotification($incident->id, $channel->id, NotificationType::Down));
        $incident->update(['resolved_at' => CarbonImmutable::parse('2026-10-17 12:05:00')]);
        Bus::dispatchSync(new SendIncidentNotification($incident->id, $channel->id, NotificationType::Recovered));

        [$trigger, $resolve] = array_column($this->outboundRequests, 'json');
        $this->assertSame('trigger', $trigger['event_action']);
        $this->assertSame("pulsecheck-incident-{$incident->id}", $trigger['dedup_key']);
        $this->assertSame('critical', $trigger['payload']['severity']);
        $this->assertSame('shop.example.com', $trigger['payload']['source']);
        $this->assertSame('Shop API', $trigger['payload']['component']);
        $this->assertSame(['routing_key' => str_repeat('r', 32), 'event_action' => 'resolve', 'dedup_key' => "pulsecheck-incident-{$incident->id}"], $resolve);
    }

    public function test_pagerduty_test_message_resolves_itself(): void
    {
        $this->fakeOutboundHttp(new Response(202), new Response(202));
        $channel = NotificationChannel::factory()->pagerDuty()->create();
        $this->actingAs(User::factory()->admin()->create())->fromSpa();

        $this->postJson("/api/notification-channels/{$channel->id}/test")->assertOk();

        $this->assertSame(['trigger', 'resolve'], array_column(array_column($this->outboundRequests, 'json'), 'event_action'));
        $this->assertSame($this->outboundRequests[0]['json']['dedup_key'], $this->outboundRequests[1]['json']['dedup_key']);
    }

    public static function validConfigs(): array
    {
        return [
            'teams' => ['microsoft_teams', ['webhook_url' => self::TEAMS_URL], ['webhook_url']],
            'google chat' => ['google_chat', ['webhook_url' => self::CHAT_URL], ['webhook_url']],
            'ntfy' => ['ntfy', ['topic' => 'acme', 'server_url' => 'https://ntfy.example.com'], ['topic']],
            'pushover' => ['pushover', ['app_token' => str_repeat('a', 30), 'user_key' => str_repeat('b', 30)], ['app_token', 'user_key']],
            'pagerduty' => ['pagerduty', ['routing_key' => str_repeat('c', 32)], ['routing_key']],
        ];
    }

    #[DataProvider('validConfigs')]
    public function test_channels_are_created_with_secrets_hidden(string $type, array $config, array $secrets): void
    {
        $this->actingAs(User::factory()->admin()->create())->fromSpa();

        $response = $this->postJson('/api/notification-channels', ['type' => $type, 'name' => 'Ops', 'config' => $config])
            ->assertCreated()
            ->assertJsonPath('data.type', $type)
            ->assertJsonPath('data.secrets_set', $secrets);

        foreach ($secrets as $secret) {
            $response->assertJsonMissingPath("data.config.{$secret}");
        }
    }

    public static function invalidConfigs(): array
    {
        return [
            'teams on another host' => ['microsoft_teams', ['webhook_url' => 'https://evil.example.com/workflows/x'], 'config.webhook_url'],
            'google chat on another host' => ['google_chat', ['webhook_url' => 'https://chat.example.com/v1/spaces/x/messages?key=1'], 'config.webhook_url'],
            'ntfy topic with slash' => ['ntfy', ['topic' => 'a/b'], 'config.topic'],
            'ntfy private server' => ['ntfy', ['topic' => 'a', 'server_url' => 'http://10.0.0.5'], 'config.server_url'],
            'pushover short key' => ['pushover', ['app_token' => 'short', 'user_key' => str_repeat('b', 30)], 'config.app_token'],
            'pagerduty bad key' => ['pagerduty', ['routing_key' => 'nope'], 'config.routing_key'],
        ];
    }

    #[DataProvider('invalidConfigs')]
    public function test_invalid_configs_are_rejected(string $type, array $config, string $field): void
    {
        $this->actingAs(User::factory()->admin()->create())->fromSpa();

        $this->postJson('/api/notification-channels', ['type' => $type, 'name' => 'Ops', 'config' => $config])
            ->assertJsonValidationErrors($field);
    }
}
