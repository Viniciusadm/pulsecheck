<?php

namespace App\Services;

use App\Enums\MaintenanceWindowType;
use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class MaintenanceSchedule
{
    private const LOOKAHEAD_DAYS = 8;

    public function activeUntil(MaintenanceWindow $window, CarbonInterface $at): ?CarbonImmutable
    {
        $at = CarbonImmutable::instance($at);

        if ($window->type === MaintenanceWindowType::OneOff) {
            return $window->starts_at !== null && $window->ends_at !== null && $window->starts_at->lessThanOrEqualTo($at) && $window->ends_at->greaterThan($at)
                ? CarbonImmutable::instance($window->ends_at)
                : null;
        }

        foreach ([0, 1] as $daysBack) {
            [$start, $end] = $this->occurrence($window, $at->setTimezone($window->timezone)->startOfDay()->subDays($daysBack));

            if ($start !== null && $start->lessThanOrEqualTo($at) && $end->greaterThan($at)) {
                return $end->utc();
            }
        }

        return null;
    }

    public function nextStart(MaintenanceWindow $window, CarbonInterface $at): ?CarbonImmutable
    {
        $at = CarbonImmutable::instance($at);

        if ($window->type === MaintenanceWindowType::OneOff) {
            return $window->starts_at !== null && $window->starts_at->greaterThan($at) ? CarbonImmutable::instance($window->starts_at) : null;
        }

        $today = $at->setTimezone($window->timezone)->startOfDay();

        foreach (range(0, self::LOOKAHEAD_DAYS) as $days) {
            [$start] = $this->occurrence($window, $today->addDays($days));

            if ($start !== null && $start->greaterThan($at)) {
                return $start->utc();
            }
        }

        return null;
    }

    public function endsAtFor(Monitor $monitor, CarbonInterface $at): ?CarbonImmutable
    {
        return MaintenanceWindow::query()
            ->affecting($monitor)
            ->notFinished($at)
            ->get()
            ->map(fn (MaintenanceWindow $window) => $this->activeUntil($window, $at))
            ->filter()
            ->sort()
            ->last();
    }

    private function occurrence(MaintenanceWindow $window, CarbonImmutable $day): array
    {
        if (! in_array($day->isoWeekday(), $window->weekdays ?? [], true)) {
            return [null, null];
        }

        $crossesMidnight = $window->end_time <= $window->start_time;

        return [
            $day->setTimeFromTimeString($window->start_time),
            ($crossesMidnight ? $day->addDay() : $day)->setTimeFromTimeString($window->end_time),
        ];
    }
}
