<?php

namespace Tests\Feature\Dashboard;

use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\MonitorHourlyStat;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCheckResults;
use Tests\TestCase;

class RollUpHourlyStatsTest extends TestCase
{
    use CreatesCheckResults;
    use RefreshDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = CarbonImmutable::parse('2026-10-18 12:07:00', 'UTC');
        $this->travelTo($this->now);
    }

    public function test_rolls_up_closed_hours_without_maintenance_checks(): void
    {
        $monitor = Monitor::factory()->create();
        foreach ([100, 200, 300] as $latency) {
            $this->checkAt($monitor, CarbonImmutable::parse('2026-10-18 10:15:00'), latencyMs: $latency);
        }
        $this->checkAt($monitor, CarbonImmutable::parse('2026-10-18 10:20:00'), isSuccess: false, latencyMs: null);
        $this->checkAt($monitor, CarbonImmutable::parse('2026-10-18 10:30:00'))->update(['in_maintenance' => true]);
        $this->checkAt($monitor, CarbonImmutable::parse('2026-10-18 12:01:00'));

        $this->artisan('metrics:rollup')->assertSuccessful();

        $row = MonitorHourlyStat::query()->sole();
        $this->assertTrue($row->hour->equalTo(CarbonImmutable::parse('2026-10-18 10:00:00', 'UTC')));
        $this->assertSame(4, $row->checks);
        $this->assertSame(3, $row->successful_checks);
        $this->assertSame(600, $row->latency_sum_ms);
        $this->assertSame(3, $row->latency_samples);
        $this->assertSame(290, $row->p95_latency_ms);
    }

    public function test_running_again_recomputes_the_hour_without_duplicating(): void
    {
        $monitor = Monitor::factory()->create();
        $this->checkAt($monitor, CarbonImmutable::parse('2026-10-18 11:10:00'));

        $this->artisan('metrics:rollup')->assertSuccessful();
        $this->checkAt($monitor, CarbonImmutable::parse('2026-10-18 11:50:00'), isSuccess: false, latencyMs: null);
        $this->artisan('metrics:rollup')->assertSuccessful();

        $row = MonitorHourlyStat::query()->sole();
        $this->assertSame(2, $row->checks);
        $this->assertSame(1, $row->successful_checks);
    }

    public function test_catches_up_on_hours_missed_while_the_scheduler_was_down(): void
    {
        $monitor = Monitor::factory()->create();
        $this->checkAt($monitor, CarbonImmutable::parse('2026-10-15 08:10:00'));
        $this->artisan('metrics:rollup', ['--hours' => 100])->assertSuccessful();
        $this->checkAt($monitor, CarbonImmutable::parse('2026-10-16 09:10:00'));
        $this->checkAt($monitor, CarbonImmutable::parse('2026-10-18 11:10:00'));

        $this->artisan('metrics:rollup')->assertSuccessful();

        $this->assertSame(
            ['2026-10-15 08:00:00', '2026-10-16 09:00:00', '2026-10-18 11:00:00'],
            MonitorHourlyStat::query()->orderBy('hour')->get()->map(fn (MonitorHourlyStat $row) => $row->hour->utc()->toDateTimeString())->all(),
        );
    }

    public function test_long_period_metrics_combine_rolled_up_hours_with_recent_checks(): void
    {
        $fast = Monitor::factory()->create(['name' => 'Fast']);
        $slow = Monitor::factory()->create(['name' => 'Slow']);
        $this->checkAt($fast, CarbonImmutable::parse('2026-10-18 09:10:00'), latencyMs: 100);
        $this->checkAt($slow, CarbonImmutable::parse('2026-10-18 09:20:00'), latencyMs: 900);
        $this->checkAt($slow, CarbonImmutable::parse('2026-10-18 09:30:00'), isSuccess: false, latencyMs: null);
        $this->artisan('metrics:rollup')->assertSuccessful();
        CheckResult::query()->delete();
        $this->checkAt($fast, CarbonImmutable::parse('2026-10-18 12:05:00'), latencyMs: 300);

        $this->actingAs(User::factory()->admin()->create())->fromSpa();

        $uptime = collect($this->getJson('/api/dashboard/uptime-trend?period=7d')->assertOk()->json('data'))->keyBy('bucket_start');
        $this->assertCount(169, $uptime);
        $this->assertSame(['bucket_start' => '2026-10-18T09:00:00Z', 'checks' => 3, 'failed_checks' => 1, 'uptime_percentage' => 66.67], $uptime['2026-10-18T09:00:00Z']);
        $this->assertSame(1, $uptime['2026-10-18T12:00:00Z']['checks']);

        $latency = collect($this->getJson('/api/dashboard/latency-trend?period=30d')->assertOk()->json('data'))->keyBy('bucket_start');
        $this->assertSame(['bucket_start' => '2026-10-18T06:00:00Z', 'average_latency_ms' => 500, 'p95_latency_ms' => 500], $latency['2026-10-18T06:00:00Z']);
        $this->assertSame(300, $latency['2026-10-18T12:00:00Z']['average_latency_ms']);

        $this->getJson('/api/dashboard/slowest-monitors?period=7d')->assertExactJson(['data' => [
            ['monitor_id' => $slow->id, 'name' => 'Slow', 'average_latency_ms' => 900],
            ['monitor_id' => $fast->id, 'name' => 'Fast', 'average_latency_ms' => 200],
        ]]);
    }

    public function test_prune_removes_hours_older_than_the_history_retention(): void
    {
        config(['pulsecheck.history_retention_days' => 30]);
        $monitor = Monitor::factory()->create();
        MonitorHourlyStat::query()->insert([
            ['monitor_id' => $monitor->id, 'hour' => '2026-09-17 11:00:00+00', 'checks' => 1, 'successful_checks' => 1],
            ['monitor_id' => $monitor->id, 'hour' => '2026-09-17 13:00:00+00', 'checks' => 1, 'successful_checks' => 1],
        ]);

        $this->artisan('model:prune', ['--model' => [MonitorHourlyStat::class]])->assertSuccessful();

        $this->assertSame(1, MonitorHourlyStat::query()->count());
    }

    public function test_rollup_is_scheduled_hourly(): void
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command ?? '', 'metrics:rollup'));

        $this->assertNotNull($event);
        $this->assertSame('2 * * * *', $event->expression);
        $this->assertTrue($event->onOneServer);
    }
}
