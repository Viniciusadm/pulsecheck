<?php

namespace Tests\Feature\Monitors;

use App\Enums\MonitorStatus;
use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitorLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_pausing_stops_scheduling_and_keeps_the_last_status(): void
    {
        $monitor = Monitor::factory()->down()->create(['next_check_at' => now()->addMinute()]);

        $this->actingAs(User::factory()->admin()->create())->fromSpa()->postJson("/api/monitors/{$monitor->id}/pause")
            ->assertOk()
            ->assertJsonPath('data.is_paused', true)
            ->assertJsonPath('data.next_check_at', null)
            ->assertJsonPath('data.status', MonitorStatus::Down->value);

        $monitor->refresh();
        $this->assertTrue($monitor->is_paused);
        $this->assertNull($monitor->next_check_at);
        $this->assertSame(MonitorStatus::Down, $monitor->status);
    }

    public function test_resuming_schedules_an_immediate_check(): void
    {
        $this->freezeSecond();
        $monitor = Monitor::factory()->paused()->create(['next_check_at' => null]);

        $this->actingAs(User::factory()->admin()->create())->fromSpa()->postJson("/api/monitors/{$monitor->id}/resume")
            ->assertOk()
            ->assertJsonPath('data.is_paused', false);

        $monitor->refresh();
        $this->assertFalse($monitor->is_paused);
        $this->assertTrue($monitor->next_check_at->equalTo(now()));
    }

    public function test_resuming_clears_the_counters_accumulated_before_the_pause(): void
    {
        $monitor = Monitor::factory()->up()->paused()->create(['consecutive_failures' => 1, 'consecutive_successes' => 3]);

        $this->actingAs(User::factory()->admin()->create())->fromSpa()->postJson("/api/monitors/{$monitor->id}/resume")->assertOk();

        $monitor->refresh();
        $this->assertSame(0, $monitor->consecutive_failures);
        $this->assertSame(0, $monitor->consecutive_successes);
        $this->assertSame(MonitorStatus::Up, $monitor->status);
    }

    public function test_resuming_an_active_monitor_does_not_reschedule_it(): void
    {
        $nextCheckAt = now()->addMinutes(3)->startOfSecond();
        $monitor = Monitor::factory()->create(['next_check_at' => $nextCheckAt]);

        $this->actingAs(User::factory()->admin()->create())->fromSpa()->postJson("/api/monitors/{$monitor->id}/resume")->assertOk();

        $this->assertTrue($monitor->fresh()->next_check_at->equalTo($nextCheckAt));
    }

    public function test_deleting_a_monitor_removes_its_history(): void
    {
        $monitor = Monitor::factory()->create();
        CheckResult::factory()->for($monitor)->count(2)->create();

        $this->actingAs(User::factory()->admin()->create())->fromSpa()->deleteJson("/api/monitors/{$monitor->id}")->assertNoContent();

        $this->assertModelMissing($monitor);
        $this->assertDatabaseCount('check_results', 0);
    }

    public function test_monitor_list_is_paginated_newest_first(): void
    {
        $user = User::factory()->admin()->create();
        Monitor::factory()->count(22)->create();

        $response = $this->actingAs($user)->fromSpa()->getJson('/api/monitors');

        $response->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 22)
            ->assertJsonPath('data.0.id', Monitor::query()->max('id'));
    }
}
