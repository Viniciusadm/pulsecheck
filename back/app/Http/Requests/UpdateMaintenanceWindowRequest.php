<?php

namespace App\Http\Requests;

use App\Models\MaintenanceWindow;
use App\Models\Monitor;

class UpdateMaintenanceWindowRequest extends StoreMaintenanceWindowRequest
{
    protected function targetCount(): int
    {
        $window = $this->window();
        $hiddenMonitors = $window->monitors()->whereNotIn('monitors.id', Monitor::query()->visibleTo($this->user())->select('monitors.id'))->count();
        $hiddenTeams = $window->teams()->whereNotIn('teams.id', $this->user()->visibleTeamIds())->count();

        return count($this->input('monitor_ids', $window->monitors()->pluck('monitors.id')->all())) + $hiddenMonitors
            + count($this->input('team_ids', $window->teams()->pluck('teams.id')->all())) + $hiddenTeams;
    }

    private function window(): MaintenanceWindow
    {
        return $this->route('maintenanceWindow');
    }
}
