<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationChannelType;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\FakesDns;
use Tests\TestCase;

class NotificationChannelApiTest extends TestCase
{
    use FakesDns;
    use RefreshDatabase;

    private const TOKEN = '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw_';

    private const SLACK_URL = 'https://hooks.slack.com/services/T000/B000/XXXXXXXXXXXXXXXXXXXXXXXX';

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeDns(['hooks.example.com' => ['93.184.215.14'], 'internal.example.com' => ['10.0.0.5']]);
    }

    public static function validChannels(): array
    {
        return [
            'telegram' => ['telegram', ['bot_token' => self::TOKEN, 'chat_id' => '-100123'], ['chat_id' => '-100123'], ['bot_token']],
            'telegram channel name' => ['telegram', ['bot_token' => self::TOKEN, 'chat_id' => '@my_status_channel'], ['chat_id' => '@my_status_channel'], ['bot_token']],
            'webhook' => ['webhook', ['url' => 'https://hooks.example.com/in', 'headers' => ['Authorization' => 'Bearer s3cret']], ['url' => 'https://hooks.example.com/in'], ['headers']],
            'webhook without headers' => ['webhook', ['url' => 'https://hooks.example.com/in'], ['url' => 'https://hooks.example.com/in'], []],
            'slack' => ['slack', ['webhook_url' => self::SLACK_URL], [], ['webhook_url']],
            'discord' => ['discord', ['webhook_url' => 'https://discord.com/api/webhooks/123456/abc_DEF-123'], [], ['webhook_url']],
            'email' => ['email', ['email' => 'ops@example.com'], ['email' => 'ops@example.com'], []],
        ];
    }

    #[DataProvider('validChannels')]
    public function test_user_can_create_each_channel_type_and_secrets_are_never_returned(string $type, array $config, array $publicConfig, array $secrets): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->fromSpa()->postJson('/api/notification-channels', [
            'type' => $type,
            'name' => 'Ops',
            'config' => $config,
            'is_default' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', $type)
            ->assertJsonPath('data.name', 'Ops')
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.is_default', true)
            ->assertJsonPath('data.secrets_set', $secrets)
            ->assertJsonPath('data.monitors_count', 0);
        $this->assertEquals($publicConfig, $response->json('data.config'));

        foreach ($secrets as $secret) {
            $this->assertStringNotContainsString(json_encode($config[$secret]), $response->getContent());
        }

        $channel = $user->notificationChannels()->sole();
        $this->assertSame(NotificationChannelType::from($type), $channel->type);
        $this->assertSame($config, $channel->config);
    }

    public function test_config_is_encrypted_at_rest(): void
    {
        $channel = NotificationChannel::factory()->telegram()->create(['config' => ['bot_token' => self::TOKEN, 'chat_id' => '1']]);

        $raw = $this->app['db']->table('notification_channels')->where('id', $channel->id)->value('config');

        $this->assertStringNotContainsString(self::TOKEN, $raw);
    }

    public static function invalidChannels(): array
    {
        return [
            'unknown type' => [['type' => 'pager', 'name' => 'x', 'config' => []], 'type'],
            'missing name' => [['type' => 'email', 'config' => ['email' => 'a@example.com']], 'name'],
            'missing config' => [['type' => 'email', 'name' => 'x'], 'config'],
            'unknown config key' => [['type' => 'email', 'name' => 'x', 'config' => ['email' => 'a@example.com', 'cc' => 'b@example.com']], 'config'],
            'bad telegram token' => [['type' => 'telegram', 'name' => 'x', 'config' => ['bot_token' => 'nope', 'chat_id' => '1']], 'config.bot_token'],
            'bad telegram chat' => [['type' => 'telegram', 'name' => 'x', 'config' => ['bot_token' => self::TOKEN, 'chat_id' => 'abc']], 'config.chat_id'],
            'webhook private host' => [['type' => 'webhook', 'name' => 'x', 'config' => ['url' => 'https://internal.example.com/']], 'config.url'],
            'webhook loopback' => [['type' => 'webhook', 'name' => 'x', 'config' => ['url' => 'http://127.0.0.1:8080/']], 'config.url'],
            'webhook bad scheme' => [['type' => 'webhook', 'name' => 'x', 'config' => ['url' => 'ftp://hooks.example.com/']], 'config.url'],
            'webhook reserved header' => [['type' => 'webhook', 'name' => 'x', 'config' => ['url' => 'https://hooks.example.com/', 'headers' => ['Host' => 'evil']]], 'config.headers'],
            'webhook invalid header name' => [['type' => 'webhook', 'name' => 'x', 'config' => ['url' => 'https://hooks.example.com/', 'headers' => ['Bad Header' => 'x']]], 'config.headers'],
            'webhook header injection' => [['type' => 'webhook', 'name' => 'x', 'config' => ['url' => 'https://hooks.example.com/', 'headers' => ['X-Token' => "a\r\nHost: evil"]]], 'config.headers.X-Token'],
            'webhook too many headers' => [['type' => 'webhook', 'name' => 'x', 'config' => ['url' => 'https://hooks.example.com/', 'headers' => array_combine(array_map(fn ($i) => "X-H{$i}", range(1, 11)), array_fill(0, 11, 'v'))]], 'config.headers'],
            'slack foreign host' => [['type' => 'slack', 'name' => 'x', 'config' => ['webhook_url' => 'https://hooks.example.com/services/x']], 'config.webhook_url'],
            'discord foreign host' => [['type' => 'discord', 'name' => 'x', 'config' => ['webhook_url' => 'https://discord.evil.com/api/webhooks/1/abc']], 'config.webhook_url'],
            'invalid email' => [['type' => 'email', 'name' => 'x', 'config' => ['email' => 'not-an-email']], 'config.email'],
        ];
    }

    #[DataProvider('invalidChannels')]
    public function test_invalid_channels_are_rejected(array $payload, string $field): void
    {
        $this->actingAs(User::factory()->create())->fromSpa()
            ->postJson('/api/notification-channels', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('notification_channels', 0);
    }

    public function test_index_lists_only_the_users_channels_with_monitor_counts(): void
    {
        $user = User::factory()->create();
        $channel = NotificationChannel::factory()->for($user)->email()->create(['name' => 'B']);
        NotificationChannel::factory()->for($user)->slack()->create(['name' => 'A']);
        NotificationChannel::factory()->create();
        Monitor::factory()->for($user)->count(2)->create()->each(fn (Monitor $monitor) => $monitor->notificationChannels()->attach($channel));

        $response = $this->actingAs($user)->fromSpa()->getJson('/api/notification-channels');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'A')
            ->assertJsonPath('data.1.name', 'B')
            ->assertJsonPath('data.1.monitors_count', 2);
        $this->assertStringNotContainsString('hooks.slack.com', $response->getContent());
    }

    public function test_update_without_secret_keeps_the_stored_secret(): void
    {
        $channel = NotificationChannel::factory()->telegram()->create(['config' => ['bot_token' => self::TOKEN, 'chat_id' => '1']]);

        $this->actingAs($channel->user)->fromSpa()->putJson("/api/notification-channels/{$channel->id}", [
            'name' => 'Renamed',
            'enabled' => false,
            'config' => ['bot_token' => '', 'chat_id' => '987654'],
        ])->assertOk()->assertJsonPath('data.secrets_set', ['bot_token'])->assertJsonPath('data.enabled', false);

        $channel->refresh();
        $this->assertSame('Renamed', $channel->name);
        $this->assertSame(['chat_id' => '987654', 'bot_token' => self::TOKEN], $channel->config);
    }

    public function test_update_can_replace_or_clear_webhook_headers(): void
    {
        $channel = NotificationChannel::factory()->webhook()->create(['config' => ['url' => 'https://hooks.example.com/a', 'headers' => ['X-Old' => '1']]]);

        $this->actingAs($channel->user)->fromSpa()->putJson("/api/notification-channels/{$channel->id}", [
            'config' => ['url' => 'https://hooks.example.com/b'],
        ])->assertOk();
        $this->assertSame(['X-Old' => '1'], $channel->refresh()->config['headers']);

        $this->actingAs($channel->user)->fromSpa()->putJson("/api/notification-channels/{$channel->id}", [
            'config' => ['url' => 'https://hooks.example.com/b', 'headers' => []],
        ])->assertOk()->assertJsonPath('data.secrets_set', []);
        $this->assertSame(['url' => 'https://hooks.example.com/b', 'headers' => []], $channel->refresh()->config);
    }

    public function test_type_cannot_be_changed_and_config_is_validated_against_the_stored_type(): void
    {
        $channel = NotificationChannel::factory()->email()->create();

        $this->actingAs($channel->user)->fromSpa()->putJson("/api/notification-channels/{$channel->id}", [
            'type' => 'webhook',
            'config' => ['url' => 'https://hooks.example.com/'],
        ])->assertUnprocessable()->assertJsonValidationErrors('config');

        $this->assertSame(NotificationChannelType::Email, $channel->refresh()->type);
    }

    public function test_deleting_a_channel_detaches_it_from_monitors(): void
    {
        $channel = NotificationChannel::factory()->create();
        $monitor = Monitor::factory()->for($channel->user)->create();
        $monitor->notificationChannels()->attach($channel);

        $this->actingAs($channel->user)->fromSpa()->deleteJson("/api/notification-channels/{$channel->id}")->assertNoContent();

        $this->assertModelMissing($channel);
        $this->assertModelExists($monitor);
        $this->assertDatabaseCount('monitor_notification_channel', 0);
    }

    public function test_other_users_channels_are_not_found(): void
    {
        $channel = NotificationChannel::factory()->create();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->fromSpa()->putJson("/api/notification-channels/{$channel->id}", ['name' => 'x'])->assertNotFound();
        $this->actingAs($intruder)->fromSpa()->deleteJson("/api/notification-channels/{$channel->id}")->assertNotFound();
        $this->actingAs($intruder)->fromSpa()->postJson("/api/notification-channels/{$channel->id}/test")->assertNotFound();

        $this->assertModelExists($channel);
    }
}
