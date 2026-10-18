<?php

namespace Tests\Feature\StateMachine;

use App\Enums\CheckErrorType;
use App\Enums\MonitorStatus;
use App\Enums\Transition;
use App\Enums\WarningType;
use App\Models\Monitor;
use App\Services\CheckOutcome;
use App\Services\MonitorStateMachine;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MonitorStateMachineTest extends TestCase
{
    private function outcome(bool $isSuccess): CheckOutcome
    {
        $now = CarbonImmutable::now();

        return new CheckOutcome(
            $now,
            $now,
            $isSuccess ? 100 : null,
            $isSuccess ? 200 : null,
            $isSuccess,
            $isSuccess ? null : CheckErrorType::Timeout,
            $isSuccess ? null : 'Operation timed out',
        );
    }

    private function monitor(MonitorStatus $status, int $successes = 0, int $failures = 0): Monitor
    {
        return Monitor::factory()->make([
            'status' => $status,
            'consecutive_successes' => $successes,
            'consecutive_failures' => $failures,
        ]);
    }

    public static function transitionTable(): array
    {
        return [
            'pending, first success' => [MonitorStatus::Pending, 0, 0, true, MonitorStatus::Pending, 1, 0, null],
            'pending, second success' => [MonitorStatus::Pending, 1, 0, true, MonitorStatus::Up, 2, 0, Transition::BecameUp],
            'pending, first failure' => [MonitorStatus::Pending, 1, 0, false, MonitorStatus::Pending, 0, 1, null],
            'pending, second failure' => [MonitorStatus::Pending, 0, 1, false, MonitorStatus::Down, 0, 2, Transition::WentDown],
            'up, success' => [MonitorStatus::Up, 5, 0, true, MonitorStatus::Up, 6, 0, null],
            'up, first failure' => [MonitorStatus::Up, 5, 0, false, MonitorStatus::Up, 0, 1, null],
            'up, second failure' => [MonitorStatus::Up, 0, 1, false, MonitorStatus::Down, 0, 2, Transition::WentDown],
            'down, failure' => [MonitorStatus::Down, 0, 2, false, MonitorStatus::Down, 0, 3, null],
            'down, first success' => [MonitorStatus::Down, 0, 3, true, MonitorStatus::Down, 1, 0, null],
            'down, second success' => [MonitorStatus::Down, 1, 0, true, MonitorStatus::Up, 2, 0, Transition::Recovered],
            'down, failure after one success' => [MonitorStatus::Down, 1, 0, false, MonitorStatus::Down, 0, 1, null],
        ];
    }

    #[DataProvider('transitionTable')]
    public function test_transition_table(
        MonitorStatus $status,
        int $successes,
        int $failures,
        bool $isSuccess,
        MonitorStatus $expectedStatus,
        int $expectedSuccesses,
        int $expectedFailures,
        ?Transition $expectedTransition,
    ): void {
        $monitor = $this->monitor($status, $successes, $failures);

        $transition = (new MonitorStateMachine)->apply($monitor, $this->outcome($isSuccess));

        $this->assertSame($expectedStatus, $monitor->status);
        $this->assertSame($expectedSuccesses, $monitor->consecutive_successes);
        $this->assertSame($expectedFailures, $monitor->consecutive_failures);
        $this->assertSame($expectedTransition, $transition);
        $this->assertFalse($monitor->exists);
    }

    public function test_isolated_failures_never_take_a_monitor_down(): void
    {
        $monitor = $this->monitor(MonitorStatus::Up, 3);
        $machine = new MonitorStateMachine;

        foreach ([false, true, false, true, false, true] as $isSuccess) {
            $this->assertNull($machine->apply($monitor, $this->outcome($isSuccess)));
            $this->assertSame(MonitorStatus::Up, $monitor->status);
        }
    }

    public function test_went_down_fires_only_once_per_outage(): void
    {
        $monitor = $this->monitor(MonitorStatus::Up, 3);
        $machine = new MonitorStateMachine;

        $transitions = array_map(fn () => $machine->apply($monitor, $this->outcome(false)), range(1, 5));

        $this->assertSame([null, Transition::WentDown, null, null, null], $transitions);
    }

    public function test_thresholds_come_from_configuration(): void
    {
        config(['pulsecheck.failures_to_down' => 3, 'pulsecheck.successes_to_up' => 1]);
        $machine = new MonitorStateMachine;

        $monitor = $this->monitor(MonitorStatus::Up);
        $this->assertNull($machine->apply($monitor, $this->outcome(false)));
        $this->assertNull($machine->apply($monitor, $this->outcome(false)));
        $this->assertSame(Transition::WentDown, $machine->apply($monitor, $this->outcome(false)));

        $this->assertSame(Transition::Recovered, $machine->apply($monitor, $this->outcome(true)));
    }

    public function test_counters_are_capped_to_fit_the_column(): void
    {
        $monitor = $this->monitor(MonitorStatus::Up, 32767);

        (new MonitorStateMachine)->apply($monitor, $this->outcome(true));

        $this->assertSame(32767, $monitor->consecutive_successes);
    }

    private function degradedOutcome(): CheckOutcome
    {
        $now = CarbonImmutable::now();

        return new CheckOutcome($now, $now, 900, 200, true, null, null, true, WarningType::SlowResponse, 'Slow');
    }

    private function sequence(MonitorStatus $status, array $results): array
    {
        $monitor = $this->monitor($status, 3);
        $machine = new MonitorStateMachine;

        $transitions = array_map(fn (string $result) => match ($result) {
            'ok' => $machine->apply($monitor, $this->outcome(true)),
            'slow' => $machine->apply($monitor, $this->degradedOutcome()),
            'fail' => $machine->apply($monitor, $this->outcome(false)),
        }, $results);

        return [$transitions, $monitor->status];
    }

    public function test_up_monitor_becomes_degraded_after_consecutive_slow_checks(): void
    {
        [$transitions, $status] = $this->sequence(MonitorStatus::Up, ['slow', 'slow', 'slow']);

        $this->assertSame([null, Transition::BecameDegraded, null], $transitions);
        $this->assertSame(MonitorStatus::Degraded, $status);
    }

    public function test_a_single_slow_check_does_not_degrade(): void
    {
        [$transitions, $status] = $this->sequence(MonitorStatus::Up, ['slow', 'ok', 'slow', 'ok']);

        $this->assertSame([null, null, null, null], $transitions);
        $this->assertSame(MonitorStatus::Up, $status);
    }

    public function test_degraded_monitor_returns_to_up_after_healthy_checks(): void
    {
        [$transitions, $status] = $this->sequence(MonitorStatus::Degraded, ['ok', 'slow', 'ok', 'ok']);

        $this->assertSame([null, null, null, Transition::DegradationResolved], $transitions);
        $this->assertSame(MonitorStatus::Up, $status);
    }

    public function test_degraded_monitor_goes_down_after_failures(): void
    {
        [$transitions, $status] = $this->sequence(MonitorStatus::Degraded, ['fail', 'fail']);

        $this->assertSame([null, Transition::WentDown], $transitions);
        $this->assertSame(MonitorStatus::Down, $status);
    }

    public function test_slow_checks_count_as_successes_for_recovery(): void
    {
        $monitor = $this->monitor(MonitorStatus::Down, 0, 3);
        $machine = new MonitorStateMachine;

        $this->assertNull($machine->apply($monitor, $this->degradedOutcome()));
        $this->assertSame(Transition::Recovered, $machine->apply($monitor, $this->degradedOutcome()));
        $this->assertSame(Transition::BecameDegraded, $machine->apply($monitor, $this->degradedOutcome()));
    }

    public function test_settled_status_follows_the_outcome(): void
    {
        $machine = new MonitorStateMachine;

        $this->assertSame(MonitorStatus::Up, $machine->settledStatus($this->outcome(true)));
        $this->assertSame(MonitorStatus::Down, $machine->settledStatus($this->outcome(false)));
        $this->assertSame(MonitorStatus::Degraded, $machine->settledStatus($this->degradedOutcome()));
    }
}
