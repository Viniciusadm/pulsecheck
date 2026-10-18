<?php

namespace Tests\Feature\History;

use App\Models\Monitor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCheckResults;
use Tests\TestCase;

class UptimeHistoryTest extends TestCase
{
    use CreatesCheckResults;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-17 12:00:00'));
    }

    public function test_daily_uptime_with_null_for_days_without_checks(): void
    {
        $monitor = Monitor::factory()->create();
        $this->checkAt($monitor, CarbonImmutable::parse('2026-10-17 01:00:00'));
        $this->checkAt($monitor, CarbonImmutable::parse('2026-10-17 11:00:00'), isSuccess: false, latencyMs: null);
        $this->checkAt($monitor, CarbonImmutable::parse('2026-10-15 23:59:59'));
        $this->checkAt($monitor, CarbonImmutable::parse('2026-10-14 23:59:59'));

        $response = $this->actingAs($monitor->user)->fromSpa()
            ->getJson("/api/monitors/{$monitor->id}/uptime-history?days=3")
            ->assertOk();

        $this->assertSame([
            ['date' => '2026-10-15', 'total_checks' => 1, 'successful_checks' => 1, 'uptime_percentage' => 100],
            ['date' => '2026-10-16', 'total_checks' => 0, 'successful_checks' => 0, 'uptime_percentage' => null],
            ['date' => '2026-10-17', 'total_checks' => 2, 'successful_checks' => 1, 'uptime_percentage' => 50],
        ], $response->json('data'));
    }

    public function test_days_default_to_30_and_are_bounded(): void
    {
        $monitor = Monitor::factory()->create();
        $this->actingAs($monitor->user)->fromSpa();

        $this->getJson("/api/monitors/{$monitor->id}/uptime-history")->assertOk()->assertJsonCount(30, 'data');
        $this->getJson("/api/monitors/{$monitor->id}/uptime-history?days=0")->assertJsonValidationErrors('days');
        $this->getJson("/api/monitors/{$monitor->id}/uptime-history?days=91")->assertJsonValidationErrors('days');
    }
}
