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
        if ($outcome->isSuccess) {
            $monitor->consecutive_successes = min($monitor->consecutive_successes + 1, self::MAX_COUNTER);
            $monitor->consecutive_failures = 0;
        } else {
            $monitor->consecutive_failures = min($monitor->consecutive_failures + 1, self::MAX_COUNTER);
            $monitor->consecutive_successes = 0;
        }

        $previousStatus = $monitor->status;

        if ($previousStatus !== MonitorStatus::Down && $monitor->consecutive_failures >= $this->failuresToDown()) {
            $monitor->status = MonitorStatus::Down;

            return Transition::WentDown;
        }

        if ($previousStatus !== MonitorStatus::Up && $monitor->consecutive_successes >= $this->successesToUp()) {
            $monitor->status = MonitorStatus::Up;

            return $previousStatus === MonitorStatus::Down ? Transition::Recovered : Transition::BecameUp;
        }

        return null;
    }

    public function failuresToDown(): int
    {
        return max(1, (int) config('pulsecheck.failures_to_down'));
    }

    public function successesToUp(): int
    {
        return max(1, (int) config('pulsecheck.successes_to_up'));
    }
}
