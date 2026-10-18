<?php

namespace Tests\Feature\Incidents;

use App\Actions\RecordCheckResult;
use App\Enums\CheckErrorType;
use App\Enums\MonitorStatus;
use App\Events\MonitorRecovered;
use App\Events\MonitorWentDown;
use App\Models\Incident;
use App\Models\Monitor;
use App\Services\CheckOutcome;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class IncidentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $clock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clock = CarbonImmutable::parse('2026-10-17 12:00:00');
        $this->travelTo($this->clock);
    }

    private function record(Monitor $monitor, bool $isSuccess, string $errorMessage = 'Operation timed out'): void
    {
        $startedAt = $this->clock;
        $this->clock = $this->clock->addMinute();
        $this->travelTo($this->clock);

        app(RecordCheckResult::class)->handle($monitor, new CheckOutcome(
            $startedAt,
            $startedAt->addMilliseconds(150),
            $isSuccess ? 150 : null,
            $isSuccess ? 200 : null,
            $isSuccess,
            $isSuccess ? null : CheckErrorType::Timeout,
            $isSuccess ? null : $errorMessage,
        ));
    }

    public function test_two_failures_open_exactly_one_incident_that_stays_single_while_down(): void
    {
        Event::fake([MonitorWentDown::class, MonitorRecovered::class]);
        $monitor = Monitor::factory()->up()->create();

        $this->record($monitor, false, 'first failure');
        $this->assertSame(0, $monitor->incidents()->count());
        $this->assertSame(MonitorStatus::Up, $monitor->fresh()->status);

        $this->record($monitor, false, 'second failure');
        $this->record($monitor, false);
        $this->record($monitor, false);

        $incident = $monitor->incidents()->sole();
        $this->assertTrue($incident->is_open);
        $this->assertSame('timeout: second failure', $incident->cause);
        $this->assertTrue($incident->started_at->equalTo(CarbonImmutable::parse('2026-10-17 12:00:00')));
        $this->assertSame(MonitorStatus::Down, $monitor->fresh()->status);
        Event::assertDispatchedTimes(MonitorWentDown::class, 1);
        Event::assertDispatched(MonitorWentDown::class, fn (MonitorWentDown $event) => $event->monitor->is($monitor) && $event->incident->is($incident));
        Event::assertNotDispatched(MonitorRecovered::class);
    }

    public function test_two_successes_resolve_the_incident_with_the_real_duration(): void
    {
        Event::fake([MonitorWentDown::class, MonitorRecovered::class]);
        $monitor = Monitor::factory()->up()->create();

        $this->record($monitor, false);
        $this->record($monitor, false);
        $this->record($monitor, false);
        $this->record($monitor, true);
        $this->assertTrue($monitor->incidents()->sole()->is_open);
        $this->assertSame(MonitorStatus::Down, $monitor->fresh()->status);

        $this->record($monitor, true);

        $incident = $monitor->incidents()->sole();
        $this->assertFalse($incident->is_open);
        $this->assertTrue($incident->resolved_at->equalTo(CarbonImmutable::parse('2026-10-17 12:03:00')));
        $this->assertSame(180, $incident->duration_seconds);
        $this->assertSame(MonitorStatus::Up, $monitor->fresh()->status);
        Event::assertDispatchedTimes(MonitorRecovered::class, 1);
        Event::assertDispatched(MonitorRecovered::class, fn (MonitorRecovered $event) => $event->incident->is($incident));
    }

    public function test_alternating_results_never_open_an_incident(): void
    {
        Event::fake([MonitorWentDown::class, MonitorRecovered::class]);
        $monitor = Monitor::factory()->up()->create();

        foreach ([false, true, false, true, false, true] as $isSuccess) {
            $this->record($monitor, $isSuccess);
        }

        $this->assertSame(0, $monitor->incidents()->count());
        $this->assertSame(MonitorStatus::Up, $monitor->fresh()->status);
        Event::assertNothingDispatched();
    }

    public function test_pending_monitor_becoming_up_is_not_a_recovery(): void
    {
        Event::fake([MonitorWentDown::class, MonitorRecovered::class]);
        $monitor = Monitor::factory()->create();

        $this->record($monitor, true);
        $this->record($monitor, true);

        $this->assertSame(MonitorStatus::Up, $monitor->fresh()->status);
        Event::assertNothingDispatched();
    }

    public function test_pending_monitor_that_fails_twice_opens_an_incident(): void
    {
        Event::fake([MonitorWentDown::class]);
        $monitor = Monitor::factory()->create();

        $this->record($monitor, false);
        $this->record($monitor, false);

        $this->assertSame(MonitorStatus::Down, $monitor->fresh()->status);
        $this->assertSame(1, $monitor->incidents()->whereNull('resolved_at')->count());
        Event::assertDispatchedTimes(MonitorWentDown::class, 1);
    }

    public function test_a_new_outage_after_recovery_opens_a_new_incident(): void
    {
        Event::fake([MonitorWentDown::class, MonitorRecovered::class]);
        $monitor = Monitor::factory()->up()->create();

        foreach ([false, false, true, true, false, false] as $isSuccess) {
            $this->record($monitor, $isSuccess);
        }

        $this->assertSame(2, $monitor->incidents()->count());
        $this->assertSame(1, $monitor->incidents()->whereNull('resolved_at')->count());
        Event::assertDispatchedTimes(MonitorWentDown::class, 2);
        Event::assertDispatchedTimes(MonitorRecovered::class, 1);
    }

    public function test_long_causes_are_truncated_to_fit_the_column(): void
    {
        $monitor = Monitor::factory()->up()->create();

        $this->record($monitor, false);
        $this->record($monitor, false, str_repeat('x', 600));

        $this->assertLessThanOrEqual(500, mb_strlen($monitor->incidents()->sole()->cause));
    }

    public function test_pausing_keeps_the_incident_open_until_the_monitor_recovers_after_resuming(): void
    {
        $monitor = Monitor::factory()->up()->create();
        $this->record($monitor, false);
        $this->record($monitor, false);

        $this->actingAs($monitor->user)->fromSpa()->postJson("/api/monitors/{$monitor->id}/pause")->assertOk();
        $this->assertTrue($monitor->incidents()->sole()->is_open);

        $this->actingAs($monitor->user)->fromSpa()->postJson("/api/monitors/{$monitor->id}/resume")->assertOk();
        $this->record($monitor, true);
        $this->record($monitor, true);

        $this->assertFalse($monitor->incidents()->sole()->is_open);
    }

    public function test_events_are_dispatched_after_commit(): void
    {
        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, new MonitorWentDown(Monitor::factory()->make(), new Incident));
        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, new MonitorRecovered(Monitor::factory()->make(), new Incident));

        $dispatched = 0;
        Event::listen(MonitorWentDown::class, function () use (&$dispatched): void {
            $dispatched++;
        });
        $monitor = Monitor::factory()->up()->create(['consecutive_successes' => 0, 'consecutive_failures' => 1]);

        try {
            DB::transaction(function () use ($monitor): void {
                $this->record($monitor, false);
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(0, $dispatched);
        $this->assertSame(0, $monitor->incidents()->count());

        $this->record($monitor, false);

        $this->assertSame(1, $dispatched);
    }
}
