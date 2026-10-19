<?php

namespace App\Http\Resources;

use App\Models\Monitor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StatusPageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $visibleMonitors = $this->monitors->filter(fn (Monitor $monitor) => $user->canViewEverything() || $monitor->isVisibleTo($user))->values();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_published' => $this->is_published,
            'show_latency' => $this->show_latency,
            'show_incidents' => $this->show_incidents,
            'uptime_days' => $this->uptime_days,
            'public_path' => "/status/{$this->slug}",
            'monitors' => $visibleMonitors->map(fn (Monitor $monitor) => [
                'monitor_id' => $monitor->id,
                'name' => $monitor->name,
                'display_name' => $monitor->pivot->display_name,
                'status' => $monitor->status,
            ])->all(),
            'hidden_monitors_count' => $this->monitors->count() - $visibleMonitors->count(),
            'team_ids' => $this->teams->modelKeys(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
