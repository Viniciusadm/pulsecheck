<?php

namespace App\Services;

use App\Enums\MonitorStatus;
use App\Enums\Transition;
use App\Models\Monitor;

class MonitorStateMachine
{
    private const MAX_COUNTER = 32767;

    public function apply(Monitor $monitor, CheckOutcome $outcome): ?Transition
    {
        $this->updateCounters($monitor, $outcome);

        $previousStatus = $monitor->status;

        if ($previousStatus !== MonitorStatus::Down && $monitor->consecutive_failures >= $this->failuresToDown()) {
            $monitor->status = MonitorStatus::Down;

            return Transition::WentDown;
        }

        if (in_array($previousStatus, [MonitorStatus::Down, MonitorStatus::Pending], true) && $monitor->consecutive_successes >= $this->successesToUp()) {
            $monitor->status = MonitorStatus::Up;

            return $previousStatus === MonitorStatus::Down ? Transition::Recovered : Transition::BecameUp;
        }

        if ($previousStatus === MonitorStatus::Up && $monitor->consecutive_degraded >= $this->degradedToConfirm()) {
            $monitor->status = MonitorStatus::Degraded;

            return Transition::BecameDegraded;
        }

        if ($previousStatus === MonitorStatus::Degraded && $monitor->consecutive_healthy >= $this->successesToUp()) {
            $monitor->status = MonitorStatus::Up;

            return Transition::DegradationResolved;
        }

        return null;
    }

    public function settledStatus(CheckOutcome $outcome): MonitorStatus
    {
        return match (true) {
            ! $outcome->isSuccess => MonitorStatus::Down,
            $outcome->isDegraded => MonitorStatus::Degraded,
            default => MonitorStatus::Up,
        };
    }

    public function failuresToDown(): int
    {
        return max(1, (int) config('pulsecheck.failures_to_down'));
    }

    public function successesToUp(): int
    {
        return max(1, (int) config('pulsecheck.successes_to_up'));
    }

    public function degradedToConfirm(): int
    {
        return max(1, (int) config('pulsecheck.degraded_to_confirm'));
    }

    private function updateCounters(Monitor $monitor, CheckOutcome $outcome): void
    {
        if (! $outcome->isSuccess) {
            $monitor->consecutive_failures = $this->increment($monitor->consecutive_failures);
            $monitor->consecutive_successes = 0;
            $monitor->consecutive_degraded = 0;
            $monitor->consecutive_healthy = 0;

            return;
        }

        $monitor->consecutive_failures = 0;
        $monitor->consecutive_successes = $this->increment($monitor->consecutive_successes);

        if ($outcome->isDegraded) {
            $monitor->consecutive_degraded = $this->increment($monitor->consecutive_degraded);
            $monitor->consecutive_healthy = 0;
        } else {
            $monitor->consecutive_healthy = $this->increment($monitor->consecutive_healthy);
            $monitor->consecutive_degraded = 0;
        }
    }

    private function increment(int $counter): int
    {
        return min($counter + 1, self::MAX_COUNTER);
    }
}
