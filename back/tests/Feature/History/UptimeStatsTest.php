<?php

namespace Tests\Feature\History;

use App\Models\Monitor;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesCheckResults;
use Tests\TestCase;

class UptimeStatsTest extends TestCase
{
    use CreatesCheckResults;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-17 12:00:00'));
    }

    public function test_uptime_is_successful_checks_over_total_checks(): void
    {
        $monitor = Monitor::factory()->create();
        foreach (range(1, 10) as $i) {
            $this->checkAt($monitor, now()->subMinutes($i * 5), isSuccess: $i > 2, latencyMs: $i > 2 ? $i * 100 : null);
        }

        $this->actingAs(User::factory()->create())->fromSpa()
            ->getJson("/api/monitors/{$monitor->id}/stats?period=24h")
            ->assertOk()
            ->assertJsonPath('data.period', '24h')
            ->assertJsonPath('data.total_checks', 10)
            ->assertJsonPath('data.successful_checks', 8)
            ->assertJsonPath('data.uptime_percentage', 80)
            ->assertJsonPath('data.average_latency_ms', 650)
            ->assertJsonPath('data.p95_latency_ms', 965);
    }

    public function test_no_checks_means_no_data_never_100_percent(): void
    {
        $monitor = Monitor::factory()->create();
        $this->checkAt($monitor, now()->subDays(2));

        $this->actingAs(User::factory()->create())->fromSpa()
            ->getJson("/api/monitors/{$monitor->id}/stats?period=24h")
            ->assertOk()
            ->assertJsonPath('data.total_checks', 0)
            ->assertJsonPath('data.successful_checks', 0)
            ->assertJsonPath('data.uptime_percentage', null)
            ->assertJsonPath('data.average_latency_ms', null)
            ->assertJsonPath('data.p95_latency_ms', null);
    }

    public function test_period_selects_the_window(): void
    {
        $monitor = Monitor::factory()->create();
        $this->checkAt($monitor, now()->subHours(2));
        $this->checkAt($monitor, now()->subDays(3), isSuccess: false, latencyMs: null);
        $this->checkAt($monitor, now()->subDays(20), isSuccess: false, latencyMs: null);
        $this->checkAt($monitor, now()->subDays(40));

        $this->actingAs(User::factory()->create())->fromSpa();

        $this->getJson("/api/monitors/{$monitor->id}/stats?period=24h")->assertJsonPath('data.total_checks', 1);
        $this->getJson("/api/monitors/{$monitor->id}/stats?period=7d")->assertJsonPath('data.total_checks', 2)->assertJsonPath('data.uptime_percentage', 50);
        $this->getJson("/api/monitors/{$monitor->id}/stats?period=30d")->assertJsonPath('data.total_checks', 3);
    }

    public function test_uptime_is_rounded_to_two_decimals(): void
    {
        $monitor = Monitor::factory()->create();
        $this->checkAt($monitor, now()->subMinutes(1));
        $this->checkAt($monitor, now()->subMinutes(2));
        $this->checkAt($monitor, now()->subMinutes(3), isSuccess: false, latencyMs: null);

        $this->actingAs(User::factory()->create())->fromSpa()
            ->getJson("/api/monitors/{$monitor->id}/stats?period=24h")
            ->assertJsonPath('data.uptime_percentage', 66.67);
    }

    public function test_failures_never_round_up_to_100_percent(): void
    {
        $monitor = Monitor::factory()->create();
        DB::insert(
            'insert into check_results (monitor_id, started_at, finished_at, latency_ms, status_code, is_success)
             select ?, ?::timestamptz - make_interval(secs => n), ?::timestamptz - make_interval(secs => n), 100, 200, n <> 1
             from generate_series(1, 30000) as n',
            [$monitor->id, now()->toIso8601String(), now()->toIso8601String()],
        );

        $this->actingAs(User::factory()->create())->fromSpa()
            ->getJson("/api/monitors/{$monitor->id}/stats?period=24h")
            ->assertJsonPath('data.successful_checks', 29999)
            ->assertJsonPath('data.uptime_percentage', 99.99);
    }

    public function test_period_is_validated(): void
    {
        $monitor = Monitor::factory()->create();

        $this->actingAs(User::factory()->create())->fromSpa()
            ->getJson("/api/monitors/{$monitor->id}/stats?period=1y")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period');
    }

    public function test_period_defaults_to_24_hours(): void
    {
        $monitor = Monitor::factory()->create();

        $this->actingAs(User::factory()->create())->fromSpa()
            ->getJson("/api/monitors/{$monitor->id}/stats")
            ->assertOk()
            ->assertJsonPath('data.period', '24h');
    }
}
