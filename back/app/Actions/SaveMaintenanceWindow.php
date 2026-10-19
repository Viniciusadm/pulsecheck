<?php

namespace App\Actions;

use App\Enums\MaintenanceWindowType;
use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveMaintenanceWindow
{
    private const ONE_OFF_FIELDS = ['starts_at', 'ends_at'];

    private const RECURRING_FIELDS = ['weekdays', 'start_time', 'end_time', 'timezone'];

    public function __construct(private SyncScopedRelation $syncScopedRelation, private SyncMaintenanceState $syncMaintenanceState) {}

    public function save(User $user, MaintenanceWindow $window, array $attributes): MaintenanceWindow
    {
        $previousMonitorIds = $window->exists ? $this->affectedMonitorIds($window) : [];

        $window = DB::transaction(function () use ($user, $window, $attributes): MaintenanceWindow {
            $window->fill(Arr::except($attributes, ['monitor_ids', 'team_ids']));
            $this->clearUnusedFields($window);

            if (! $window->exists) {
                $window->creator()->associate($user);
            }

            $window->save();

            if (array_key_exists('monitor_ids', $attributes)) {
                $this->syncScopedRelation->handle($window->monitors(), $attributes['monitor_ids'], Monitor::query()->visibleTo($user)->pluck('id')->all());
            }

            if (array_key_exists('team_ids', $attributes)) {
                $this->syncScopedRelation->handle($window->teams(), $attributes['team_ids'], $user->visibleTeamIds());
            }

            return $window;
        });

        $this->syncMaintenanceState->handle(array_values(array_unique([...$previousMonitorIds, ...$this->affectedMonitorIds($window)])));

        return $window;
    }

    public function delete(MaintenanceWindow $window): void
    {
        $monitorIds = $this->affectedMonitorIds($window);

        $window->delete();

        $this->syncMaintenanceState->handle($monitorIds);
    }

    private function clearUnusedFields(MaintenanceWindow $window): void
    {
        $unused = $window->type === MaintenanceWindowType::OneOff ? self::RECURRING_FIELDS : self::ONE_OFF_FIELDS;

        foreach ($unused as $field) {
            $window->{$field} = null;
        }
    }

    private function affectedMonitorIds(MaintenanceWindow $window): array
    {
        return Monitor::query()
            ->where(fn ($monitors) => $monitors
                ->whereHas('maintenanceWindows', fn ($query) => $query->whereKey($window->id))
                ->orWhereHas('teams', fn ($teams) => $teams->whereHas('maintenanceWindows', fn ($query) => $query->whereKey($window->id))))
            ->pluck('id')
            ->all();
    }
}
