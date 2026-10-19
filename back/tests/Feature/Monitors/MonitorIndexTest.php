<?php

namespace Tests\Feature\Monitors;

use App\Models\Monitor;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MonitorIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitors_can_be_filtered_by_status(): void
    {
        $user = User::factory()->admin()->create();
        $up = Monitor::factory()->up()->create();
        $down = Monitor::factory()->down()->create();
        $pending = Monitor::factory()->create();
        $pausedDown = Monitor::factory()->down()->paused()->create();

        $this->actingAs($user)->fromSpa();

        $this->assertSame([$up->id], $this->getJson('/api/monitors?status=up')->assertOk()->json('data.*.id'));
        $this->assertSame([$down->id], $this->getJson('/api/monitors?status=down')->assertOk()->json('data.*.id'));
        $this->assertSame([$pending->id], $this->getJson('/api/monitors?status=pending')->assertOk()->json('data.*.id'));
        $this->assertSame([$pausedDown->id], $this->getJson('/api/monitors?status=paused')->assertOk()->json('data.*.id'));
        $this->assertCount(4, $this->getJson('/api/monitors')->assertOk()->json('data'));
    }

    public function test_listing_does_not_query_permissions_per_monitor(): void
    {
        $team = Team::factory()->create();
        $manager = User::factory()->manager()->create();
        $manager->teams()->attach($team);
        Monitor::factory()->count(5)->create(['badge_token' => fn () => Str::random(40)])->each(fn (Monitor $monitor) => $monitor->teams()->attach($team));

        $this->actingAs($manager)->fromSpa();
        DB::enableQueryLog();

        $monitors = $this->getJson('/api/monitors')->assertOk()->json('data');

        $this->assertCount(5, $monitors);
        $this->assertNotNull($monitors[0]['badge_url']);
        $this->assertLessThanOrEqual(8, count(DB::getQueryLog()));
    }

    public function test_unknown_status_filter_is_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->getJson('/api/monitors?status=broken')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }
}
