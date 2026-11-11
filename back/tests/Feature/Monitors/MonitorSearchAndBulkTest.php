<?php

namespace Tests\Feature\Monitors;

use App\Models\Monitor;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitorSearchAndBulkTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitors_can_be_searched_by_name_or_url(): void
    {
        $this->actingAs(User::factory()->admin()->create())->fromSpa();
        $shop = Monitor::factory()->create(['name' => 'Shop API', 'url' => 'https://shop.example.com']);
        $docs = Monitor::factory()->create(['name' => 'Docs', 'url' => 'https://docs.example.com/100%_ready']);
        Monitor::factory()->create(['name' => 'Blog', 'url' => 'https://blog.example.com']);

        $this->getJson('/api/monitors?search=shop')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $shop->id);
        $this->getJson('/api/monitors?search=DOCS.example')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $docs->id);
        $this->getJson('/api/monitors?search=100%25_')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $docs->id);
        $this->getJson('/api/monitors?search=%25')->assertJsonCount(1, 'data');
        $this->getJson('/api/monitors?search=')->assertJsonCount(3, 'data');
    }

    public function test_bulk_pause_resume_and_delete(): void
    {
        $this->actingAs(User::factory()->admin()->create())->fromSpa();
        [$first, $second] = Monitor::factory()->count(2)->create();

        $this->postJson('/api/monitors/bulk', ['action' => 'pause', 'ids' => [$first->id, $second->id]])->assertOk()->assertJsonPath('data.affected', 2);
        $this->assertTrue($first->fresh()->is_paused);
        $this->assertTrue($second->fresh()->is_paused);

        $this->postJson('/api/monitors/bulk', ['action' => 'resume', 'ids' => [$first->id]])->assertOk();
        $this->assertFalse($first->fresh()->is_paused);

        $this->postJson('/api/monitors/bulk', ['action' => 'delete', 'ids' => [$second->id]])->assertOk();
        $this->assertModelMissing($second);
    }

    public function test_bulk_actions_skip_monitors_outside_the_users_teams(): void
    {
        $team = Team::factory()->create();
        $manager = User::factory()->manager()->create();
        $manager->teams()->attach($team);
        $own = Monitor::factory()->create();
        $own->teams()->attach($team);
        $foreign = Monitor::factory()->create();

        $this->actingAs($manager)->fromSpa()
            ->postJson('/api/monitors/bulk', ['action' => 'delete', 'ids' => [$own->id, $foreign->id]])
            ->assertOk()
            ->assertJsonPath('data.affected', 1);

        $this->assertModelMissing($own);
        $this->assertModelExists($foreign);
    }

    public function test_members_cannot_run_bulk_actions(): void
    {
        $monitor = Monitor::factory()->create();

        $this->actingAs(User::factory()->create(['can_view_all_monitors' => true]))->fromSpa()
            ->postJson('/api/monitors/bulk', ['action' => 'delete', 'ids' => [$monitor->id]])
            ->assertForbidden();

        $this->assertModelExists($monitor);
    }

    public function test_bulk_action_validation(): void
    {
        $this->actingAs(User::factory()->admin()->create())->fromSpa();

        $this->postJson('/api/monitors/bulk', ['action' => 'explode', 'ids' => []])->assertJsonValidationErrors(['action', 'ids']);
        $this->postJson('/api/monitors/bulk', ['action' => 'pause', 'ids' => range(1, 101)])->assertJsonValidationErrors('ids');
    }
}
