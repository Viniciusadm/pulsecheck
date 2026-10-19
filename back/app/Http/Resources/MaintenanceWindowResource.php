<?php

namespace App\Http\Resources;

use App\Models\Monitor;
use App\Services\MaintenanceSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceWindowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $schedule = app(MaintenanceSchedule::class);
        $user = $request->user();
        $visibleTeamIds = $user->visibleTeamIds();
        $monitors = $this->monitors->filter(fn (Monitor $monitor) => $user->canViewEverything() || $monitor->isVisibleTo($user))->values();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'weekdays' => $this->weekdays,
            'start_time' => $this->start_time === null ? null : substr($this->start_time, 0, 5),
            'end_time' => $this->end_time === null ? null : substr($this->end_time, 0, 5),
            'timezone' => $this->timezone,
            'monitors' => $monitors->map(fn (Monitor $monitor) => ['id' => $monitor->id, 'name' => $monitor->name])->all(),
            'monitor_ids' => $monitors->modelKeys(),
            'team_ids' => array_values(array_intersect($this->teams->modelKeys(), $visibleTeamIds)),
            'active_until' => $schedule->activeUntil($this->resource, now()),
            'next_starts_at' => $schedule->nextStart($this->resource, now()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
