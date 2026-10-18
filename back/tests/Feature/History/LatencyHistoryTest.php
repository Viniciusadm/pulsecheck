<?php

namespace Tests\Feature\History;

use App\Models\Monitor;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCheckResults;
use Tests\TestCase;

class LatencyHistoryTest extends TestCase
{
    use CreatesCheckResults;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-17 12:07:00'));
    }

    public function test_24h_series_has_15_minute_buckets_with_averages_and_gaps(): void
    {
        $monitor = Monitor::factory()->create();
        $this->checkAt($monitor, CarbonImmutable::parse('2026-10-17 11:46:00'), latencyMs: 100);
        $this->checkAt($monitor, CarbonImmutable::parse('2026-10-17 11:59:59'), latencyMs: 300);
        $this->checkAt($monitor, CarbonImmutable::parse('2026-10-17 11:50:00'), isSuccess: false, latencyMs: null);
        $this->checkAt($monitor, CarbonImmutable::parse('2026-10-17 12:00:00'), latencyMs: 50);

        $response = $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->getJson("/api/monitors/{$monitor->id}/latency?period=24h")
            ->assertOk()
            ->assertJsonPath('data.period', '24h')
            ->assertJsonPath('data.bucket_minutes', 15);

        $buckets = collect($response->json('data.buckets'))->keyBy('bucket_start');

        $this->assertCount(97, $buckets);
        $this->assertSame(['bucket_start' => '2026-10-17T11:45:00Z', 'average_latency_ms' => 200, 'checks' => 3, 'failed_checks' => 1], $buckets['2026-10-17T11:45:00Z']);
        $this->assertSame(['bucket_start' => '2026-10-17T12:00:00Z', 'average_latency_ms' => 50, 'checks' => 1, 'failed_checks' => 0], $buckets['2026-10-17T12:00:00Z']);
        $this->assertSame(['bucket_start' => '2026-10-17T11:30:00Z', 'average_latency_ms' => null, 'checks' => 0, 'failed_checks' => 0], $buckets['2026-10-17T11:30:00Z']);
        $this->assertSame('2026-10-16T12:00:00Z', $buckets->keys()->first());
    }

    public function test_bucket_size_depends_on_the_period(): void
    {
        $monitor = Monitor::factory()->create();
        $this->actingAs(User::factory()->admin()->create())->fromSpa();

        $this->getJson("/api/monitors/{$monitor->id}/latency?period=7d")->assertJsonPath('data.bucket_minutes', 60)->assertJsonCount(169, 'data.buckets');
        $this->getJson("/api/monitors/{$monitor->id}/latency?period=30d")->assertJsonPath('data.bucket_minutes', 360)->assertJsonCount(121, 'data.buckets');
        $this->getJson("/api/monitors/{$monitor->id}/latency?period=1h")->assertJsonValidationErrors('period');
    }
}
