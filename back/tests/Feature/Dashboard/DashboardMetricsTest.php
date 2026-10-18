<?php

namespace Tests\Feature\Dashboard;

use App\Enums\CheckErrorType;
use App\Models\CheckResult;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesCheckResults;
use Tests\TestCase;

class DashboardMetricsTest extends TestCase
{
    use CreatesCheckResults, RefreshDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = CarbonImmutable::parse('2026-10-18 12:07:00', 'UTC');
        $this->travelTo($this->now);
    }

    public function test_uptime_trend_aggregates_all_visible_monitors_per_bucket(): void
    {
        $first = Monitor::factory()->create();
        $second = Monitor::factory()->create();
        $this->checkAt($first, $this->now->subMinutes(5));
        $this->checkAt($second, $this->now->subMinutes(4), isSuccess: false);
        $this->checkAt($first, $this->now->subMinutes(30));

        $this->actingAs(User::factory()->admin()->create())->fromSpa();
        DB::enableQueryLog();

        $buckets = $this->getJson('/api/dashboard/uptime-trend?period=24h')->assertOk()->json('data');

        $this->assertCount(97, $buckets);
        $this->assertSame(['bucket_start' => '2026-10-18T12:00:00Z', 'checks' => 2, 'failed_checks' => 1, 'uptime_percentage' => 50], end($buckets));
        $this->assertSame(['bucket_start' => '2026-10-18T11:30:00Z', 'checks' => 1, 'failed_checks' => 0, 'uptime_percentage' => 100], $buckets[94]);
        $this->assertSame(['bucket_start' => '2026-10-18T11:45:00Z', 'checks' => 0, 'failed_checks' => 0, 'uptime_percentage' => null], $buckets[95]);
        $this->assertCount(1, collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], '"check_results"')));
    }

    public function test_latency_trend_returns_average_and_p95_of_successful_checks(): void
    {
        $monitor = Monitor::factory()->create();
        foreach ([100, 200, 300] as $latency) {
            $this->checkAt($monitor, $this->now->subMinutes(2), latencyMs: $latency);
        }
        $this->checkAt($monitor, $this->now->subMinutes(2), isSuccess: false, latencyMs: 9000);

        $buckets = $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->getJson('/api/dashboard/latency-trend?period=24h')
            ->json('data');

        $this->assertSame(['bucket_start' => '2026-10-18T12:00:00Z', 'average_latency_ms' => 200, 'p95_latency_ms' => 290], end($buckets));
        $this->assertNull($buckets[0]['average_latency_ms']);
    }

    public function test_slowest_monitors_are_ranked_by_average_latency(): void
    {
        $fast = Monitor::factory()->create(['name' => 'Fast']);
        $slow = Monitor::factory()->create(['name' => 'Slow']);
        Monitor::factory()->create(['name' => 'Silent']);
        $this->checkAt($fast, $this->now->subHour(), latencyMs: 100);
        $this->checkAt($slow, $this->now->subHour(), latencyMs: 800);
        $this->checkAt($slow, $this->now->subHour(), latencyMs: 600);
        $this->checkAt($slow, $this->now->subHour(), isSuccess: false, latencyMs: 30000);
        $this->checkAt($fast, $this->now->subDays(2), latencyMs: 5000);

        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->getJson('/api/dashboard/slowest-monitors?period=24h')
            ->assertExactJson(['data' => [
                ['monitor_id' => $slow->id, 'name' => 'Slow', 'average_latency_ms' => 700],
                ['monitor_id' => $fast->id, 'name' => 'Fast', 'average_latency_ms' => 100],
            ]]);
    }

    public function test_incident_timeline_counts_incidents_by_start(): void
    {
        $monitor = Monitor::factory()->create();
        Incident::factory()->for($monitor)->create(['started_at' => $this->now->subHours(3), 'resolved_at' => $this->now->subHours(2)]);
        Incident::factory()->for($monitor)->open()->create(['started_at' => $this->now->subMinutes(5)]);
        Incident::factory()->for($monitor)->create(['started_at' => $this->now->subDays(8), 'resolved_at' => $this->now->subDays(8)]);

        $buckets = collect($this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->getJson('/api/dashboard/incident-timeline?period=7d')
            ->assertOk()
            ->json('data'));

        $this->assertCount(169, $buckets);
        $this->assertSame(2, $buckets->sum('incidents'));
        $this->assertSame(1, $buckets->firstWhere('bucket_start', '2026-10-18T09:00:00Z')['incidents']);
        $this->assertSame(1, $buckets->last()['incidents']);
    }

    public function test_error_breakdown_counts_failed_checks_by_type(): void
    {
        $monitor = Monitor::factory()->create();
        $this->checkAt($monitor, $this->now->subHour(), isSuccess: false);
        $this->checkAt($monitor, $this->now->subHour(), isSuccess: false);
        $this->checkAt($monitor, $this->now->subHour());
        CheckResult::factory()->for($monitor)->create([
            'started_at' => $this->now->subHour(),
            'is_success' => false,
            'latency_ms' => null,
            'status_code' => 503,
            'error_type' => CheckErrorType::HttpStatus,
        ]);

        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->getJson('/api/dashboard/error-breakdown')
            ->assertExactJson(['data' => [
                ['error_type' => 'timeout', 'failed_checks' => 2],
                ['error_type' => 'http_status', 'failed_checks' => 1],
            ]]);
    }

    public function test_metrics_only_include_monitors_visible_to_the_user(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create();
        $user->teams()->attach($team);
        $visible = Monitor::factory()->create(['name' => 'Visible']);
        $visible->teams()->attach($team);
        $hidden = Monitor::factory()->create(['name' => 'Hidden']);
        $hidden->teams()->attach(Team::factory()->create());

        $this->checkAt($visible, $this->now->subMinutes(2), latencyMs: 100);
        $this->checkAt($hidden, $this->now->subMinutes(2), isSuccess: false, latencyMs: 900);
        Incident::factory()->for($hidden)->open()->create(['started_at' => $this->now->subMinutes(2)]);

        $this->actingAs($user)->fromSpa();

        $this->assertSame(1, collect($this->getJson('/api/dashboard/uptime-trend')->json('data'))->sum('checks'));
        $this->assertSame(0, collect($this->getJson('/api/dashboard/incident-timeline')->json('data'))->sum('incidents'));
        $this->assertSame(['Visible'], collect($this->getJson('/api/dashboard/slowest-monitors')->json('data'))->pluck('name')->all());
        $this->getJson('/api/dashboard/error-breakdown')->assertExactJson(['data' => []]);
    }

    public function test_rejects_unknown_periods(): void
    {
        $this->actingAs(User::factory()->create())->fromSpa()
            ->getJson('/api/dashboard/latency-trend?period=1y')
            ->assertJsonValidationErrors('period');
    }
}
