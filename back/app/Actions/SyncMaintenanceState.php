<?php

namespace App\Actions;

use App\Events\MonitorChecked;
use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use App\Services\MaintenanceSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SyncMaintenanceState
{
    public function __construct(private MaintenanceSchedule $schedule) {}

    public function handle(?array $monitorIds = null): int
    {
        $changed = 0;

        $this->candidates($monitorIds)->each(function (Monitor $monitor) use (&$changed): void {
            if ($this->sync($monitor)) {
                $changed++;
            }
        });

        return $changed;
    }

    private function candidates(?array $monitorIds): Builder
    {
        if ($monitorIds !== null) {
            return Monitor::query()->whereKey($monitorIds);
        }

        $windows = MaintenanceWindow::query()->notFinished()->select('maintenance_windows.id');

        return Monitor::query()->where(fn (Builder $monitors) => $monitors
            ->whereNotNull('maintenance_ends_at')
            ->orWhereHas('maintenanceWindows', fn (Builder $query) => $query->whereIn('maintenance_windows.id', $windows))
            ->orWhereHas('teams', fn (Builder $teams) => $teams->whereHas('maintenanceWindows', fn (Builder $query) => $query->whereIn('maintenance_windows.id', $windows))));
    }

    private function sync(Monitor $monitor): bool
    {
        $monitor = DB::transaction(function () use ($monitor): ?Monitor {
            $monitor = Monitor::query()->lockForUpdate()->find($monitor->id);

            if ($monitor === null) {
                return null;
            }

            $endsAt = $this->schedule->endsAtFor($monitor, now());
            $current = $monitor->maintenance_ends_at;

            if ($endsAt?->getTimestamp() === $current?->getTimestamp()) {
                return null;
            }

            $monitor->maintenance_ends_at = $endsAt;

            if ($endsAt === null && $monitor->consecutive_failures > 0 && ! $monitor->is_paused) {
                $monitor->next_check_at = now();
            }

            $monitor->save();

            return $monitor;
        });

        if ($monitor === null) {
            return false;
        }

        rescue(fn () => MonitorChecked::dispatch($monitor));

        return true;
    }
}
