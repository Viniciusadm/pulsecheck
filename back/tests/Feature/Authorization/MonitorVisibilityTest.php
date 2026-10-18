<?php

namespace Tests\Feature\Authorization;

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitorVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private Monitor $teamMonitor;

    private Monitor $otherTeamMonitor;

    private Monitor $teamlessMonitor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::factory()->create();
        $this->teamMonitor = Monitor::factory()->up()->create();
        $this->teamMonitor->teams()->attach($this->team);
        $this->otherTeamMonitor = Monitor::factory()->down()->create();
        $this->otherTeamMonitor->teams()->attach(Team::factory()->create());
        $this->teamlessMonitor = Monitor::factory()->create();
    }

    private function teamMember(): User
    {
        $user = User::factory()->create();
        $user->teams()->attach($this->team);

        return $user;
    }

    public function test_members_list_only_monitors_of_their_teams(): void
    {
        $this->actingAs($this->teamMember())->fromSpa()->getJson('/api/monitors')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$this->teamMonitor->id])
            ->assertJsonPath('data.0.team_ids', [$this->team->id]);
    }

    public function test_admins_and_users_viewing_everything_see_every_monitor(): void
    {
        $everyMonitor = [$this->teamlessMonitor->id, $this->otherTeamMonitor->id, $this->teamMonitor->id];

        $this->actingAs(User::factory()->admin()->create())->fromSpa()->getJson('/api/monitors')
            ->assertJsonPath('data.*.id', $everyMonitor);

        $this->actingAs(User::factory()->viewingAll()->create())->getJson('/api/monitors')
            ->assertJsonPath('data.*.id', $everyMonitor);
    }

    public function test_monitors_outside_the_users_teams_are_not_found(): void
    {
        $this->actingAs($this->teamMember())->fromSpa();

        foreach ([$this->otherTeamMonitor, $this->teamlessMonitor] as $monitor) {
            $this->getJson("/api/monitors/{$monitor->id}")->assertNotFound();
            $this->getJson("/api/monitors/{$monitor->id}/checks")->assertNotFound();
            $this->getJson("/api/monitors/{$monitor->id}/stats")->assertNotFound();
        }

        $this->getJson("/api/monitors/{$this->teamMonitor->id}")->assertOk();
        $this->getJson("/api/monitors/{$this->teamMonitor->id}/stats")->assertOk();
    }

    public function test_members_are_read_only(): void
    {
        $this->actingAs($this->teamMember())->fromSpa();
        $monitor = $this->teamMonitor;

        $this->postJson('/api/monitors', ['name' => 'x', 'url' => 'https://example.com', 'team_ids' => [$this->team->id]])->assertForbidden();
        $this->putJson("/api/monitors/{$monitor->id}", ['name' => 'Renamed'])->assertForbidden();
        $this->postJson("/api/monitors/{$monitor->id}/pause")->assertForbidden();
        $this->postJson("/api/monitors/{$monitor->id}/resume")->assertForbidden();
        $this->deleteJson("/api/monitors/{$monitor->id}")->assertForbidden();

        $this->assertSame($monitor->name, $monitor->refresh()->name);
        $this->assertFalse($monitor->is_paused);
    }

    public function test_managers_cannot_touch_monitors_outside_their_teams(): void
    {
        $manager = User::factory()->manager()->create();
        $manager->teams()->attach($this->team);
        $this->actingAs($manager)->fromSpa();

        $this->putJson("/api/monitors/{$this->otherTeamMonitor->id}", ['name' => 'x'])->assertNotFound();
        $this->deleteJson("/api/monitors/{$this->otherTeamMonitor->id}")->assertNotFound();
        $this->postJson("/api/monitors/{$this->teamMonitor->id}/pause")->assertOk();
        $this->deleteJson("/api/monitors/{$this->teamMonitor->id}")->assertNoContent();
    }

    public function test_dashboard_summary_counts_only_visible_monitors(): void
    {
        $this->actingAs($this->teamMember())->fromSpa()->getJson('/api/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.up', 1)
            ->assertJsonPath('data.down', 0);
    }

    public function test_recent_incidents_include_only_visible_monitors(): void
    {
        $visible = Incident::factory()->for($this->teamMonitor)->create();
        Incident::factory()->for($this->otherTeamMonitor)->create();
        Incident::factory()->for($this->teamlessMonitor)->create();

        $this->actingAs($this->teamMember())->fromSpa()->getJson('/api/incidents')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$visible->id]);
    }
}
