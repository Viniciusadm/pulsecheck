<?php

namespace Tests\Feature\StatusPages;

use App\Models\Monitor;
use App\Models\StatusPage;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StatusPageManagementTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::factory()->create();
    }

    private function teamMonitor(string $name = 'API'): Monitor
    {
        $monitor = Monitor::factory()->create(['name' => $name]);
        $monitor->teams()->attach($this->team);

        return $monitor;
    }

    private function manager(): User
    {
        $manager = User::factory()->manager()->create();
        $manager->teams()->attach($this->team);

        return $manager;
    }

    public function test_manager_creates_a_status_page_with_ordered_monitors(): void
    {
        $api = $this->teamMonitor('API');
        $web = $this->teamMonitor('Website');

        $this->actingAs($this->manager())->fromSpa()
            ->postJson('/api/status-pages', [
                'name' => 'Acme status',
                'slug' => 'acme',
                'is_published' => true,
                'team_ids' => [$this->team->id],
                'monitors' => [
                    ['monitor_id' => $web->id, 'display_name' => 'Site'],
                    ['monitor_id' => $api->id],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.public_path', '/status/acme')
            ->assertJsonPath('data.monitors.0.monitor_id', $web->id)
            ->assertJsonPath('data.monitors.0.display_name', 'Site')
            ->assertJsonPath('data.monitors.1.monitor_id', $api->id)
            ->assertJsonPath('data.hidden_monitors_count', 0);
    }

    public static function invalidPayloads(): array
    {
        return [
            'slug with spaces' => [['slug' => 'acme status'], 'slug'],
            'slug uppercase' => [['slug' => 'Acme'], 'slug'],
            'reserved slug' => [['slug' => 'admin'], 'slug'],
            'uptime days' => [['uptime_days' => 45], 'uptime_days'],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_pages_are_rejected(array $overrides, string $field): void
    {
        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->postJson('/api/status-pages', ['name' => 'Acme', 'slug' => 'acme', ...$overrides])
            ->assertJsonValidationErrors($field);
    }

    public function test_slugs_are_unique_but_a_page_keeps_its_own(): void
    {
        $page = StatusPage::factory()->create(['slug' => 'acme']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->fromSpa()->postJson('/api/status-pages', ['name' => 'Other', 'slug' => 'acme'])->assertJsonValidationErrors('slug');
        $this->actingAs($admin)->fromSpa()->patchJson("/api/status-pages/{$page->id}", ['slug' => 'acme', 'name' => 'Renamed'])->assertOk();
    }

    public function test_members_read_pages_but_cannot_change_them(): void
    {
        $page = StatusPage::factory()->create();
        $page->teams()->attach($this->team);
        $member = User::factory()->create();
        $member->teams()->attach($this->team);

        $this->actingAs($member)->fromSpa()->getJson('/api/status-pages')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($member)->fromSpa()->postJson('/api/status-pages', ['name' => 'X', 'slug' => 'x'])->assertForbidden();
        $this->actingAs($member)->fromSpa()->deleteJson("/api/status-pages/{$page->id}")->assertForbidden();
    }

    public function test_managers_cannot_see_other_teams_pages_or_add_their_monitors(): void
    {
        $otherTeam = Team::factory()->create();
        $hidden = StatusPage::factory()->create();
        $hidden->teams()->attach($otherTeam);
        $foreignMonitor = Monitor::factory()->create();
        $foreignMonitor->teams()->attach($otherTeam);
        $manager = $this->manager();

        $this->actingAs($manager)->fromSpa()->getJson("/api/status-pages/{$hidden->id}")->assertNotFound();
        $this->actingAs($manager)->fromSpa()
            ->postJson('/api/status-pages', ['name' => 'X', 'slug' => 'x', 'team_ids' => [$this->team->id], 'monitors' => [['monitor_id' => $foreignMonitor->id]]])
            ->assertJsonValidationErrors('monitors.0.monitor_id');
    }

    public function test_saving_keeps_hidden_monitors_in_their_positions(): void
    {
        $otherTeam = Team::factory()->create();
        $foreign = Monitor::factory()->create();
        $foreign->teams()->attach($otherTeam);
        $first = $this->teamMonitor('First');
        $second = $this->teamMonitor('Second');
        $page = StatusPage::factory()->create();
        $page->teams()->attach($this->team);
        $page->monitors()->sync([
            $first->id => ['sort_order' => 0],
            $foreign->id => ['sort_order' => 1],
            $second->id => ['sort_order' => 2],
        ]);

        $this->actingAs($this->manager())->fromSpa()
            ->patchJson("/api/status-pages/{$page->id}", ['monitors' => [['monitor_id' => $second->id], ['monitor_id' => $first->id]]])
            ->assertOk()
            ->assertJsonPath('data.hidden_monitors_count', 1)
            ->assertJsonCount(2, 'data.monitors');

        $this->assertSame([$second->id, $foreign->id, $first->id], $page->monitors()->pluck('monitors.id')->all());
    }
}
