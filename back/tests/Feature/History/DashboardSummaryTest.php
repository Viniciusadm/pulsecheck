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
        $user = User::factory()->create();
        Monitor::factory()->for($user)->up()->count(3)->create();
        Monitor::factory()->for($user)->down()->count(2)->create();
        Monitor::factory()->for($user)->create();
        Monitor::factory()->for($user)->up()->paused()->create();
        Monitor::factory()->for($user)->down()->paused()->create();
        Monitor::factory()->down()->count(4)->create();

        $this->actingAs($user)->fromSpa();
        DB::enableQueryLog();

        $this->getJson('/api/dashboard/summary')
            ->assertOk()
            ->assertExactJson(['data' => ['total' => 8, 'up' => 3, 'down' => 2, 'pending' => 1, 'paused' => 2]]);

        $monitorQueries = collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], '"monitors"'));
        $this->assertCount(1, $monitorQueries);
    }

    public function test_user_without_monitors_gets_zeros(): void
    {
        $this->actingAs(User::factory()->create())->fromSpa()
            ->getJson('/api/dashboard/summary')
            ->assertExactJson(['data' => ['total' => 0, 'up' => 0, 'down' => 0, 'pending' => 0, 'paused' => 0]]);
    }
}
