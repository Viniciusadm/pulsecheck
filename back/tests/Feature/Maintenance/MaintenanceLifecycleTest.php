<?php

namespace Tests\Feature\Maintenance;

use App\Actions\RecordCheckResult;
use App\Enums\CheckErrorType;
use App\Enums\MonitorStatus;
use App\Events\MonitorRecovered;
use App\Events\MonitorWentDown;
use App\Models\Incident;
use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use App\Models\User;
use App\Services\CheckOutcome;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MaintenanceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $clock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clock = CarbonImmutable::parse('2026-10-17 12:00:00');
        $this->travelTo($this->clock);
    }

    private function record(Monitor $monitor, bool $isSuccess): void
    {
        $startedAt = $this->clock;
        $this->clock = $this->clock->addMinute();
        $this->travelTo($this->clock);

        app(RecordCheckResult::class)->handle($monitor, new CheckOutcome(
            $startedAt,
            $startedAt,
            $isSuccess ? 100 : null,
            $isSuccess ? 200 : null,
            $isSuccess,
            $isSuccess ? null : CheckErrorType::Timeout,
            $isSuccess ? null : 'Operation timed out',
        ));
    }

    private function maintenance(Monitor $monitor, string $from, string $to): MaintenanceWindow
    {
        $window = MaintenanceWindow::factory()->create(['starts_at' => $from, 'ends_at' => $to]);
        $window->monitors()->attach($monitor);

        return $window;
    }

    public function test_failures_during_maintenance_open_no_incident_and_schedule_no_confirmation(): void
    {
        Event::fake([MonitorWentDown::class]);
        $monitor = Monitor::factory()->up()->create(['interval_minutes' => 10, 'next_check_at' => now()->addMinutes(10)]);
        $this->maintenance($monitor, '2026-10-17 11:00:00', '2026-10-17 13:00:00');

        $this->record($monitor, false);
        $this->record($monitor, false);
        $this->record($monitor, false);

        $monitor->refresh();
        $this->assertSame(MonitorStatus::Up, $monitor->status);
        $this->assertSame(3, $monitor->consecutive_failures);
        $this->assertTrue($monitor->maintenance_ends_at->equalTo(CarbonImmutable::parse('2026-10-17 13:00:00')));
        $this->assertTrue($monitor->next_check_at->equalTo(CarbonImmutable::parse('2026-10-17 12:10:00')));
        $this->assertSame(0, $monitor->incidents()->count());
        $this->assertSame(3, $monitor->checkResults()->where('in_maintenance', true)->count());
        Event::assertNotDispatched(MonitorWentDown::class);
    }

    public function test_still_failing_after_the_window_opens_an_incident_that_starts_after_it(): void
    {
        Event::fake([MonitorWentDown::class]);
        $monitor = Monitor::factory()->up()->create();
        $this->maintenance($monitor, '2026-10-17 11:00:00', '2026-10-17 12:02:00');

        $this->record($monitor, false);
        $this->record($monitor, false);
        $this->record($monitor, false);

        $incident = $monitor->incidents()->sole();
        $this->assertSame(MonitorStatus::Down, $monitor->fresh()->status);
        $this->assertNull($monitor->fresh()->maintenance_ends_at);
        $this->assertTrue($incident->started_at->equalTo(CarbonImmutable::parse('2026-10-17 12:02:00')));
        Event::assertDispatchedTimes(MonitorWentDown::class, 1);
    }

    public function test_a_monitor_that_was_down_recovers_during_maintenance(): void
    {
        Event::fake([MonitorRecovered::class]);
        $monitor = Monitor::factory()->down()->create();
        Incident::factory()->for($monitor)->open()->create(['started_at' => now()->subHour()]);
        $this->maintenance($monitor, '2026-10-17 11:00:00', '2026-10-17 13:00:00');

        $this->record($monitor, true);
        $this->record($monitor, true);

        $this->assertSame(MonitorStatus::Up, $monitor->fresh()->status);
        $this->assertFalse($monitor->incidents()->sole()->is_open);
        Event::assertDispatchedTimes(MonitorRecovered::class, 1);
    }

    public function test_checks_in_maintenance_are_left_out_of_uptime_and_shown_per_day(): void
    {
        $monitor = Monitor::factory()->up()->create();
        $this->record($monitor, true);
        $this->maintenance($monitor, '2026-10-17 12:01:00', '2026-10-17 13:00:00');
        $this->record($monitor, false);
        $this->record($monitor, false);

        $this->actingAs(User::factory()->admin()->create())->fromSpa();

        $this->getJson("/api/monitors/{$monitor->id}/stats?period=24h")
            ->assertJsonPath('data.total_checks', 1)
            ->assertJsonPath('data.uptime_percentage', 100);

        $this->getJson("/api/monitors/{$monitor->id}/uptime-history?days=1")
            ->assertJsonPath('data.0.total_checks', 1)
            ->assertJsonPath('data.0.maintenance_checks', 2);

        $this->getJson('/api/dashboard/summary')
            ->assertJsonPath('data.maintenance', 1)
            ->assertJsonPath('data.up', 0);

        $this->getJson('/api/monitors?status=maintenance')->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_in_maintenance', true);
        $this->getJson('/api/monitors?status=up')->assertJsonCount(0, 'data');
    }
}
