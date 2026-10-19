<?php

namespace Tests\Feature\Teams;

use App\Models\Monitor;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_updates_and_deletes_a_team(): void
    {
        $this->actingAs(User::factory()->admin()->create())->fromSpa();

        $teamId = $this->postJson('/api/teams', ['name' => '  Backend  '])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Backend')
            ->assertJsonPath('data.users_count', 0)
            ->json('data.id');

        $this->putJson("/api/teams/{$teamId}", ['name' => 'Platform'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Platform');

        $this->deleteJson("/api/teams/{$teamId}")->assertNoContent();

        $this->assertDatabaseMissing('teams', ['id' => $teamId]);
    }

    public function test_admin_manages_team_members_and_monitors(): void
    {
        $team = Team::factory()->create();
        [$alice, $bob] = User::factory()->count(2)->create();
        [$api, $site] = Monitor::factory()->count(2)->create();
        $team->users()->attach($alice);
        $team->monitors()->attach($api);
        $this->actingAs(User::factory()->admin()->create())->fromSpa();

        $this->getJson("/api/teams/{$team->id}")
            ->assertOk()
            ->assertJsonPath('data.user_ids', [$alice->id])
            ->assertJsonPath('data.monitor_ids', [$api->id]);

        $this->putJson("/api/teams/{$team->id}", ['name' => $team->name, 'user_ids' => [$bob->id], 'monitor_ids' => [$api->id, $site->id]])
            ->assertOk()
            ->assertJsonPath('data.user_ids', [$bob->id])
            ->assertJsonPath('data.users_count', 1)
            ->assertJsonPath('data.monitors_count', 2);

        $this->putJson("/api/teams/{$team->id}", ['name' => 'Renamed'])->assertOk();

        $this->assertEqualsCanonicalizing([$bob->id], $team->users()->pluck('users.id')->all());
        $this->assertEqualsCanonicalizing([$api->id, $site->id], $team->monitors()->pluck('monitors.id')->all());
    }

    public function test_admin_creates_a_team_with_members_and_monitors(): void
    {
        $member = User::factory()->create();
        $monitor = Monitor::factory()->create();
        $this->actingAs(User::factory()->admin()->create())->fromSpa();

        $this->postJson('/api/teams', ['name' => 'Ops', 'user_ids' => [$member->id], 'monitor_ids' => [$monitor->id]])
            ->assertCreated()
            ->assertJsonPath('data.user_ids', [$member->id])
            ->assertJsonPath('data.monitor_ids', [$monitor->id]);
    }

    public function test_admins_cannot_be_team_members(): void
    {
        $team = Team::factory()->create();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->fromSpa();

        $this->putJson("/api/teams/{$team->id}", ['name' => $team->name, 'user_ids' => [$admin->id]])
            ->assertJsonValidationErrors('user_ids.0');
    }

    public function test_team_names_are_unique(): void
    {
        $team = Team::factory()->create(['name' => 'Backend']);
        $other = Team::factory()->create();
        $this->actingAs(User::factory()->admin()->create())->fromSpa();

        $this->postJson('/api/teams', ['name' => 'Backend'])->assertJsonValidationErrors('name');
        $this->putJson("/api/teams/{$other->id}", ['name' => 'Backend'])->assertJsonValidationErrors('name');
        $this->putJson("/api/teams/{$team->id}", ['name' => 'Backend'])->assertOk();
    }

    public function test_managers_and_members_cannot_manage_teams(): void
    {
        $team = Team::factory()->create();

        foreach ([User::factory()->manager()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)->fromSpa();

            $this->postJson('/api/teams', ['name' => 'Ops'])->assertForbidden();
            $this->getJson("/api/teams/{$team->id}")->assertForbidden();
            $this->putJson("/api/teams/{$team->id}", ['name' => 'Ops'])->assertForbidden();
            $this->deleteJson("/api/teams/{$team->id}")->assertForbidden();
        }

        $this->assertModelExists($team);
    }

    public function test_index_lists_only_the_users_teams_unless_they_can_view_everything(): void
    {
        $alpha = Team::factory()->create(['name' => 'Alpha']);
        $beta = Team::factory()->create(['name' => 'Beta']);
        $member = User::factory()->create();
        $member->teams()->attach($beta);

        $this->actingAs($member)->fromSpa()->getJson('/api/teams')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$beta->id]);

        $this->actingAs(User::factory()->viewingAll()->create())->getJson('/api/teams')
            ->assertJsonPath('data.*.id', [$alpha->id, $beta->id]);

        $this->actingAs(User::factory()->admin()->create())->getJson('/api/teams')
            ->assertJsonPath('data.*.id', [$alpha->id, $beta->id])
            ->assertJsonPath('data.1.users_count', 1);
    }

    public function test_deleting_a_team_hides_its_monitors_from_its_former_members(): void
    {
        $team = Team::factory()->create();
        $monitor = Monitor::factory()->create();
        $monitor->teams()->attach($team);
        $member = User::factory()->create();
        $member->teams()->attach($team);

        $this->actingAs(User::factory()->admin()->create())->fromSpa()->deleteJson("/api/teams/{$team->id}")->assertNoContent();

        $this->actingAs($member)->getJson("/api/monitors/{$monitor->id}")->assertNotFound();
        $this->assertModelExists($monitor);
    }
}
