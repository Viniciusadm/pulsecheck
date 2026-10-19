<?php

namespace Tests\Feature\History;

use App\Models\Monitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_counts_paused_separately_from_status_in_a_single_query(): void
    {
        $user = User::factory()->admin()->create();
        Monitor::factory()->up()->count(3)->create();
        Monitor::factory()->down()->count(2)->create();
        Monitor::factory()->create();
        Monitor::factory()->up()->paused()->create();
        Monitor::factory()->down()->paused()->create();

        $this->actingAs($user)->fromSpa();
        DB::enableQueryLog();

        $this->getJson('/api/dashboard/summary')
            ->assertOk()
            ->assertExactJson(['data' => ['total' => 8, 'up' => 3, 'down' => 2, 'degraded' => 0, 'pending' => 1, 'maintenance' => 0, 'paused' => 2]]);

        $monitorQueries = collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], '"monitors"'));
        $this->assertCount(1, $monitorQueries);
    }

    public function test_user_without_monitors_gets_zeros(): void
    {
        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->getJson('/api/dashboard/summary')
            ->assertExactJson(['data' => ['total' => 0, 'up' => 0, 'down' => 0, 'degraded' => 0, 'pending' => 0, 'maintenance' => 0, 'paused' => 0]]);
    }
}
