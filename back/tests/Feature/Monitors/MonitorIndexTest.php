<?php

namespace Tests\Feature\Monitors;

use App\Models\Monitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitorIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitors_can_be_filtered_by_status(): void
    {
        $user = User::factory()->create();
        $up = Monitor::factory()->for($user)->up()->create();
        $down = Monitor::factory()->for($user)->down()->create();
        $pending = Monitor::factory()->for($user)->create();
        $pausedDown = Monitor::factory()->for($user)->down()->paused()->create();
        Monitor::factory()->up()->create();

        $this->actingAs($user)->fromSpa();

        $this->assertSame([$up->id], $this->getJson('/api/monitors?status=up')->assertOk()->json('data.*.id'));
        $this->assertSame([$down->id], $this->getJson('/api/monitors?status=down')->assertOk()->json('data.*.id'));
        $this->assertSame([$pending->id], $this->getJson('/api/monitors?status=pending')->assertOk()->json('data.*.id'));
        $this->assertSame([$pausedDown->id], $this->getJson('/api/monitors?status=paused')->assertOk()->json('data.*.id'));
        $this->assertCount(4, $this->getJson('/api/monitors')->assertOk()->json('data'));
    }

    public function test_unknown_status_filter_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())->fromSpa()
            ->getJson('/api/monitors?status=broken')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }
}
