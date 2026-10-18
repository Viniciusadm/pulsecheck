<?php

namespace Tests\Feature\Monitors;

use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesDns;
use Tests\TestCase;

class MonitorNotificationChannelsTest extends TestCase
{
    use FakesDns;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeDns(['shop.example.com' => ['93.184.215.14']]);
    }

    public function test_new_monitors_get_the_default_channels_when_none_are_given(): void
    {
        $user = User::factory()->create();
        $default = NotificationChannel::factory()->asDefault()->create();
        NotificationChannel::factory()->create();

        $response = $this->actingAs($user)->fromSpa()->postJson('/api/monitors', ['name' => 'Shop', 'url' => 'https://shop.example.com']);

        $response->assertCreated()->assertJsonPath('data.notification_channel_ids', [$default->id]);
    }

    public function test_new_monitors_use_the_given_channels_even_when_empty(): void
    {
        $user = User::factory()->create();
        NotificationChannel::factory()->asDefault()->create();
        $chosen = NotificationChannel::factory()->create();

        $this->actingAs($user)->fromSpa()
            ->postJson('/api/monitors', ['name' => 'Shop', 'url' => 'https://shop.example.com', 'notification_channel_ids' => [$chosen->id]])
            ->assertCreated()
            ->assertJsonPath('data.notification_channel_ids', [$chosen->id]);

        $this->actingAs($user)->fromSpa()
            ->postJson('/api/monitors', ['name' => 'Quiet', 'url' => 'https://shop.example.com', 'notification_channel_ids' => []])
            ->assertCreated()
            ->assertJsonPath('data.notification_channel_ids', []);
    }

    public function test_update_syncs_channels_only_when_given(): void
    {
        $monitor = Monitor::factory()->create();
        [$first, $second] = NotificationChannel::factory()->count(2)->create();
        $monitor->notificationChannels()->attach($first);

        $this->actingAs(User::factory()->create())->fromSpa()
            ->patchJson("/api/monitors/{$monitor->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.notification_channel_ids', [$first->id]);

        $this->actingAs(User::factory()->create())->fromSpa()
            ->patchJson("/api/monitors/{$monitor->id}", ['notification_channel_ids' => [$second->id]])
            ->assertOk()
            ->assertJsonPath('data.notification_channel_ids', [$second->id]);
    }

    public function test_unknown_channels_are_rejected(): void
    {
        $user = User::factory()->create();
        $unknownChannelId = 999999;
        $monitor = Monitor::factory()->create();

        $this->actingAs($user)->fromSpa()
            ->postJson('/api/monitors', ['name' => 'Shop', 'url' => 'https://shop.example.com', 'notification_channel_ids' => [$unknownChannelId]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('notification_channel_ids.0');

        $this->actingAs($user)->fromSpa()
            ->patchJson("/api/monitors/{$monitor->id}", ['notification_channel_ids' => [$unknownChannelId]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('notification_channel_ids.0');

        $this->assertDatabaseCount('monitor_notification_channel', 0);
    }

    public function test_index_includes_channel_ids(): void
    {
        $monitor = Monitor::factory()->create();
        $channel = NotificationChannel::factory()->create();
        $monitor->notificationChannels()->attach($channel);

        $this->actingAs(User::factory()->create())->fromSpa()
            ->getJson('/api/monitors')
            ->assertOk()
            ->assertJsonPath('data.0.notification_channel_ids', [$channel->id]);
    }
}
