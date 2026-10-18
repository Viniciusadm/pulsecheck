<?php

namespace Tests\Feature\Monitors;

use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesDns;
use Tests\TestCase;

class MonitorTeamAssignmentTest extends TestCase
{
    use FakesDns;
    use RefreshDatabase;

    private Team $team;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeDns(['example.com' => ['93.184.215.14']]);

        $this->team = Team::factory()->create();
        $this->manager = User::factory()->manager()->create();
        $this->manager->teams()->attach($this->team);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Website',
            'url' => 'https://example.com',
            ...$overrides,
        ];
    }

    public function test_managers_create_monitors_in_their_teams(): void
    {
        $this->actingAs($this->manager)->fromSpa()
            ->postJson('/api/monitors', $this->payload(['team_ids' => [$this->team->id]]))
            ->assertCreated()
            ->assertJsonPath('data.team_ids', [$this->team->id]);
    }

    public function test_managers_must_assign_at_least_one_of_their_teams(): void
    {
        $foreignTeam = Team::factory()->create();
        $this->actingAs($this->manager)->fromSpa();

        $this->postJson('/api/monitors', $this->payload())->assertJsonValidationErrors('team_ids');
        $this->postJson('/api/monitors', $this->payload(['team_ids' => []]))->assertJsonValidationErrors('team_ids');
        $this->postJson('/api/monitors', $this->payload(['team_ids' => [$foreignTeam->id]]))->assertJsonValidationErrors('team_ids.0');

        $this->assertSame(0, Monitor::query()->count());
    }

    public function test_users_viewing_everything_may_create_monitors_without_teams(): void
    {
        $team = Team::factory()->create();
        $this->actingAs(User::factory()->manager()->viewingAll()->create())->fromSpa();

        $this->postJson('/api/monitors', $this->payload())->assertCreated()->assertJsonPath('data.team_ids', []);
        $this->postJson('/api/monitors', $this->payload(['team_ids' => [$team->id]]))->assertCreated()->assertJsonPath('data.team_ids', [$team->id]);
    }

    public function test_updating_teams_keeps_teams_outside_the_users_scope(): void
    {
        $foreignTeam = Team::factory()->create();
        $secondTeam = Team::factory()->create();
        $this->manager->teams()->attach($secondTeam);
        $monitor = Monitor::factory()->create();
        $monitor->teams()->attach([$this->team->id, $foreignTeam->id]);

        $this->actingAs($this->manager)->fromSpa()
            ->putJson("/api/monitors/{$monitor->id}", ['team_ids' => [$secondTeam->id]])
            ->assertOk();

        $this->assertEqualsCanonicalizing([$foreignTeam->id, $secondTeam->id], $monitor->teams()->pluck('teams.id')->all());
    }

    public function test_only_visible_notification_channels_can_be_attached(): void
    {
        $visible = NotificationChannel::factory()->create();
        $visible->teams()->attach($this->team);
        $hidden = NotificationChannel::factory()->create();
        $this->actingAs($this->manager)->fromSpa();

        $this->postJson('/api/monitors', $this->payload(['team_ids' => [$this->team->id], 'notification_channel_ids' => [$hidden->id]]))
            ->assertJsonValidationErrors('notification_channel_ids.0');

        $this->postJson('/api/monitors', $this->payload(['team_ids' => [$this->team->id], 'notification_channel_ids' => [$visible->id]]))
            ->assertCreated()
            ->assertJsonPath('data.notification_channel_ids', [$visible->id]);
    }

    public function test_default_channels_attached_on_creation_are_limited_to_visible_ones(): void
    {
        $visibleDefault = NotificationChannel::factory()->create(['is_default' => true]);
        $visibleDefault->teams()->attach($this->team);
        NotificationChannel::factory()->create(['is_default' => true]);

        $this->actingAs($this->manager)->fromSpa()
            ->postJson('/api/monitors', $this->payload(['team_ids' => [$this->team->id]]))
            ->assertCreated()
            ->assertJsonPath('data.notification_channel_ids', [$visibleDefault->id]);
    }

    public function test_updating_channels_keeps_channels_the_user_cannot_see(): void
    {
        $hidden = NotificationChannel::factory()->create();
        $visible = NotificationChannel::factory()->create();
        $visible->teams()->attach($this->team);
        $monitor = Monitor::factory()->create();
        $monitor->teams()->attach($this->team);
        $monitor->notificationChannels()->attach([$hidden->id, $visible->id]);

        $this->actingAs($this->manager)->fromSpa()
            ->putJson("/api/monitors/{$monitor->id}", ['notification_channel_ids' => []])
            ->assertOk();

        $this->assertSame([$hidden->id], $monitor->notificationChannels()->pluck('notification_channels.id')->all());
    }
}
