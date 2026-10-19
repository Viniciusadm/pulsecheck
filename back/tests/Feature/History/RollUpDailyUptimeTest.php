<?php

namespace Tests\Feature\History;

use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\MonitorDailyUptime;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCheckResults;
use Tests\TestCase;

class RollUpDailyUptimeTest extends TestCase
{
    use CreatesCheckResults;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-17 12:00:00'));
    }

    public function test_rolls_up_only_closed_days_within_the_window(): void
    {
        $monitor = Monitor::factory()->create();
        $this->checkAt($monitor, CarbonImmutable::parse('2026-10-16 00:00:00'), latencyMs: 100);
        $this->checkAt($monitor, CarbonImmutable::parse('2026-10-16 23:59:59'), latencyMs: 300);
        $this->checkAt($monitor, CarbonImmutable::parse('2026-10-16 10:00:00'), isSuccess: false, latencyMs: null);
        $this->checkAt($monitor, CarbonImmutable::parse('2026-10-15 10:00:00'));
        $this->checkAt($monitor, CarbonImmutable::parse('2026-10-14 10:00:00'));
        $this->checkAt($monitor, CarbonImmutable::parse('2026-10-17 01:00:00'));

        $this->artisan('uptime:rollup')->assertSuccessful();

        $rows = MonitorDailyUptime::query()->orderBy('date')->get();

        $this->assertSame(['2026-10-15', '2026-10-16'], $rows->map(fn ($row) => $row->date->toDateString())->all());
        $this->assertSame(1, $rows[0]->total_checks);
        $this->assertSame(3, $rows[1]->total_checks);
        $this->assertSame(2, $rows[1]->successful_checks);
        $this->assertSame(400, $rows[1]->latency_sum_ms);
        $this->assertSame(2, $rows[1]->latency_samples);
    }

    public function test_running_again_recomputes_the_day_without_duplicating(): void
    {
        $monitor = Monitor::factory()->create();
        $this->checkAt($monitor, CarbonImmutable::parse('2026-10-16 10:00:00'));

        $this->artisan('uptime:rollup')->assertSuccessful();
        $this->checkAt($monitor, CarbonImmutable::parse('2026-10-16 11:00:00'), isSuccess: false, latencyMs: null);
        $this->artisan('uptime:rollup')->assertSuccessful();

        $row = MonitorDailyUptime::query()->sole();
        $this->assertSame(2, $row->total_checks);
        $this->assertSame(1, $row->successful_checks);
    }

    public function test_catches_up_on_days_missed_while_the_scheduler_was_down(): void
    {
        $monitor = Monitor::factory()->create();
        $this->checkAt($monitor, CarbonImmutable::parse('2026-10-10 10:00:00'));
        $this->artisan('uptime:rollup', ['--days' => 10])->assertSuccessful();
        $this->checkAt($monitor, CarbonImmutable::parse('2026-10-12 10:00:00'));
        $this->checkAt($monitor, CarbonImmutable::parse('2026-10-16 10:00:00'));

        $this->artisan('uptime:rollup')->assertSuccessful();

        $this->assertSame(
            ['2026-10-10', '2026-10-12', '2026-10-16'],
            MonitorDailyUptime::query()->orderBy('date')->pluck('date')->map->toDateString()->all(),
        );
    }

    public function test_daily_history_keeps_rolled_up_days_after_results_are_pruned(): void
    {
        config(['pulsecheck.history_retention_days' => 30]);
        $monitor = Monitor::factory()->create();
        $this->checkAt($monitor, CarbonImmutable::parse('2026-08-18 10:00:00'));
        $this->checkAt($monitor, CarbonImmutable::parse('2026-08-18 11:00:00'), isSuccess: false, latencyMs: null);
        $this->checkAt($monitor, CarbonImmutable::parse('2026-10-16 10:00:00'));
        $this->checkAt($monitor, CarbonImmutable::parse('2026-10-17 10:00:00'));

        $this->artisan('uptime:rollup', ['--days' => 90])->assertSuccessful();
        $this->artisan('model:prune', ['--model' => [CheckResult::class]])->assertSuccessful();

        $days = collect($this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->getJson("/api/monitors/{$monitor->id}/uptime-history?days=90")
            ->assertOk()
            ->json('data'))
            ->keyBy('date');

        $this->assertCount(90, $days);
        $this->assertSame(['date' => '2026-08-18', 'total_checks' => 2, 'successful_checks' => 1, 'degraded_checks' => 0, 'maintenance_checks' => 0, 'uptime_percentage' => 50], $days['2026-08-18']);
        $this->assertSame(1, $days['2026-10-16']['total_checks']);
        $this->assertSame(1, $days['2026-10-17']['total_checks']);
        $this->assertNull($days['2026-08-19']['uptime_percentage']);
    }

    public function test_recent_days_are_read_from_check_results_even_before_the_rollup_runs(): void
    {
        $monitor = Monitor::factory()->create();
        $this->checkAt($monitor, CarbonImmutable::parse('2026-10-16 10:00:00'));

        $days = $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->getJson("/api/monitors/{$monitor->id}/uptime-history?days=2")
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $days[0]['total_checks']);
    }

    public function test_prune_removes_rollups_older_than_their_retention(): void
    {
        config(['pulsecheck.uptime_rollup_retention_days' => 400]);
        $monitor = Monitor::factory()->create();
        MonitorDailyUptime::query()->insert([
            ['monitor_id' => $monitor->id, 'date' => '2025-09-11', 'total_checks' => 1, 'successful_checks' => 1],
            ['monitor_id' => $monitor->id, 'date' => '2025-09-12', 'total_checks' => 1, 'successful_checks' => 1],
        ]);

        $this->artisan('model:prune', ['--model' => [MonitorDailyUptime::class]])->assertSuccessful();

        $this->assertSame(['2025-09-12'], MonitorDailyUptime::query()->pluck('date')->map->toDateString()->all());
    }

    public function test_rollup_is_scheduled_hourly(): void
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command ?? '', 'uptime:rollup'));

        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);
        $this->assertTrue($event->onOneServer);
    }
}
