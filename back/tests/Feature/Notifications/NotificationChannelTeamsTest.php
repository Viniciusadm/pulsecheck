<?php

namespace Tests\Feature\Notifications;

use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationChannelTeamsTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private NotificationChannel $teamChannel;

    private NotificationChannel $otherChannel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::factory()->create();
        $this->teamChannel = NotificationChannel::factory()->create(['name' => 'Team channel']);
        $this->teamChannel->teams()->attach($this->team);
        $this->otherChannel = NotificationChannel::factory()->create(['name' => 'Other channel']);
    }

    private function userInTeam(User $user): User
    {
        $user->teams()->attach($this->team);

        return $user;
    }

    private function emailPayload(array $overrides = []): array
    {
        return [
            'type' => 'email',
            'name' => 'Ops',
            'config' => ['email' => 'ops@example.com'],
            ...$overrides,
        ];
    }

    public function test_index_lists_only_channels_of_the_users_teams(): void
    {
        $visibleMonitor = Monitor::factory()->create();
        $visibleMonitor->teams()->attach($this->team);
        $hiddenMonitor = Monitor::factory()->create();
        $this->teamChannel->monitors()->attach([$visibleMonitor->id, $hiddenMonitor->id]);

        $this->actingAs($this->userInTeam(User::factory()->create()))->fromSpa()->getJson('/api/notification-channels')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$this->teamChannel->id])
            ->assertJsonPath('data.0.team_ids', [$this->team->id])
            ->assertJsonPath('data.0.monitors_count', 1);

        $this->actingAs(User::factory()->admin()->create())->getJson('/api/notification-channels')
            ->assertJsonPath('data.*.id', [$this->otherChannel->id, $this->teamChannel->id]);
    }

    public function test_members_cannot_manage_channels(): void
    {
        $this->actingAs($this->userInTeam(User::factory()->create()))->fromSpa();
        $channel = $this->teamChannel;

        $this->postJson('/api/notification-channels', $this->emailPayload(['team_ids' => [$this->team->id]]))->assertForbidden();
        $this->putJson("/api/notification-channels/{$channel->id}", ['name' => 'x'])->assertForbidden();
        $this->postJson("/api/notification-channels/{$channel->id}/test")->assertForbidden();
        $this->deleteJson("/api/notification-channels/{$channel->id}")->assertForbidden();

        $this->assertModelExists($channel);
    }

    public function test_managers_manage_channels_of_their_teams_only(): void
    {
        $this->actingAs($this->userInTeam(User::factory()->manager()->create()))->fromSpa();

        $this->postJson('/api/notification-channels', $this->emailPayload())->assertJsonValidationErrors('team_ids');
        $this->postJson('/api/notification-channels', $this->emailPayload(['team_ids' => [$this->team->id]]))
            ->assertCreated()
            ->assertJsonPath('data.team_ids', [$this->team->id]);

        $this->putJson("/api/notification-channels/{$this->teamChannel->id}", ['name' => 'Renamed'])->assertOk();
        $this->putJson("/api/notification-channels/{$this->otherChannel->id}", ['name' => 'x'])->assertNotFound();
        $this->deleteJson("/api/notification-channels/{$this->otherChannel->id}")->assertNotFound();
        $this->deleteJson("/api/notification-channels/{$this->teamChannel->id}")->assertNoContent();

        $this->assertModelExists($this->otherChannel);
    }

    public function test_updating_channel_teams_keeps_teams_outside_the_users_scope(): void
    {
        $foreignTeam = Team::factory()->create();
        $this->teamChannel->teams()->attach($foreignTeam);
        $secondTeam = Team::factory()->create();
        $manager = $this->userInTeam(User::factory()->manager()->create());
        $manager->teams()->attach($secondTeam);

        $this->actingAs($manager)->fromSpa()
            ->putJson("/api/notification-channels/{$this->teamChannel->id}", ['team_ids' => [$secondTeam->id]])
            ->assertOk();

        $this->assertEqualsCanonicalizing([$foreignTeam->id, $secondTeam->id], $this->teamChannel->teams()->pluck('teams.id')->all());
    }

    public function test_members_do_not_receive_channel_config(): void
    {
        $this->teamChannel->update(['config' => ['email' => 'ops@example.com']]);

        $this->actingAs($this->userInTeam(User::factory()->create()))->fromSpa()->getJson('/api/notification-channels')
            ->assertOk()
            ->assertJsonPath('data.0.config', []);

        $this->actingAs($this->userInTeam(User::factory()->manager()->create()))->fromSpa()->getJson('/api/notification-channels')
            ->assertOk()
            ->assertJsonPath('data.0.config', ['email' => 'ops@example.com']);
    }

    public function test_monitor_lists_only_channels_visible_to_the_user(): void
    {
        $monitor = Monitor::factory()->create();
        $monitor->teams()->attach($this->team);
        $monitor->notificationChannels()->attach([$this->teamChannel->id, $this->otherChannel->id]);

        $this->actingAs($this->userInTeam(User::factory()->create()))->fromSpa()->getJson('/api/monitors')
            ->assertOk()
            ->assertJsonPath('data.0.notification_channel_ids', [$this->teamChannel->id]);

        $this->actingAs($this->userInTeam(User::factory()->create()))->fromSpa()->getJson("/api/monitors/{$monitor->id}")
            ->assertOk()
            ->assertJsonPath('data.notification_channel_ids', [$this->teamChannel->id]);

        $this->actingAs(User::factory()->admin()->create())->fromSpa()->getJson("/api/monitors/{$monitor->id}")
            ->assertOk()
            ->assertJsonPath('data.notification_channel_ids', [$this->teamChannel->id, $this->otherChannel->id]);
    }
}
