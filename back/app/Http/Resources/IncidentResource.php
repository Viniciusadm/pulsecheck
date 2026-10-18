<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncidentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'started_at' => $this->started_at,
            'resolved_at' => $this->resolved_at,
            'cause' => $this->cause,
            'state' => $this->state,
            'duration_seconds' => $this->duration_seconds,
            'monitor' => $this->whenLoaded('monitor', fn () => [
                'id' => $this->monitor->id,
                'name' => $this->monitor->name,
            ]),
        ];
    }
}
