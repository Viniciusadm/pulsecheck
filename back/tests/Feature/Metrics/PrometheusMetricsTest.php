<?php

namespace Tests\Feature\Metrics;

use App\Models\CheckResult;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrometheusMetricsTest extends TestCase
{
    use RefreshDatabase;

    private function scrape(User $user): string
    {
        $token = $user->createToken('prometheus', ['read'])->plainTextToken;

        return $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Accept' => 'text/plain'])
            ->get('/api/metrics')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8')
            ->getContent();
    }

    public function test_it_exports_monitor_metrics_in_prometheus_format(): void
    {
        $up = Monitor::factory()->up()->create(['name' => 'Shop "API"', 'last_latency_ms' => 125, 'last_checked_at' => now()]);
        $down = Monitor::factory()->down()->create(['name' => 'Docs']);
        $paused = Monitor::factory()->paused()->create(['name' => 'Old']);
        Incident::factory()->for($down)->open()->create();
        CheckResult::factory()->for($up)->count(3)->create(['started_at' => now()->subHour(), 'is_success' => true, 'in_maintenance' => false]);
        CheckResult::factory()->for($up)->create(['started_at' => now()->subHour(), 'is_success' => false, 'in_maintenance' => false]);
        CheckResult::factory()->for($up)->create(['started_at' => now()->subHour(), 'is_success' => false, 'in_maintenance' => true]);
        CheckResult::factory()->for($up)->create(['started_at' => now()->subDays(2), 'is_success' => false, 'in_maintenance' => false]);

        $body = $this->scrape(User::factory()->admin()->create());

        $upLabels = "id=\"{$up->id}\",name=\"Shop \\\"API\\\"\",type=\"http\"";
        $this->assertStringContainsString("# TYPE pulsecheck_monitor_up gauge\n", $body);
        $this->assertStringContainsString("pulsecheck_monitor_up{{$upLabels}} 1\n", $body);
        $this->assertStringContainsString("pulsecheck_monitor_up{id=\"{$down->id}\",name=\"Docs\",type=\"http\"} 0\n", $body);
        $this->assertStringNotContainsString("pulsecheck_monitor_up{id=\"{$paused->id}\"", $body);
        $this->assertStringContainsString("pulsecheck_monitor_status{id=\"{$paused->id}\",name=\"Old\",type=\"http\",status=\"paused\"} 1\n", $body);
        $this->assertStringContainsString("pulsecheck_monitor_response_time_seconds{{$upLabels}} 0.125\n", $body);
        $this->assertStringContainsString("pulsecheck_monitor_uptime_ratio{{$upLabels},window=\"24h\"} 0.75\n", $body);
        $this->assertStringContainsString("pulsecheck_monitor_open_incident{id=\"{$down->id}\",name=\"Docs\",type=\"http\"} 1\n", $body);
        $this->assertStringContainsString("pulsecheck_monitors 3\n", $body);
    }

    public function test_only_monitors_visible_to_the_token_owner_are_exported(): void
    {
        $team = Team::factory()->create();
        $member = User::factory()->create();
        $member->teams()->attach($team);
        $visible = Monitor::factory()->create(['name' => 'Visible']);
        $visible->teams()->attach($team);
        Monitor::factory()->create(['name' => 'Hidden']);

        $body = $this->scrape($member);

        $this->assertStringContainsString('name="Visible"', $body);
        $this->assertStringNotContainsString('name="Hidden"', $body);
        $this->assertStringContainsString("pulsecheck_monitors 1\n", $body);
    }

    public function test_metrics_require_authentication(): void
    {
        $this->getJson('/api/metrics')->assertUnauthorized();
    }
}
