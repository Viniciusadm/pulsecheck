<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonitorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'interval_minutes' => $this->interval_minutes,
            'timeout_seconds' => $this->timeout_seconds,
            'status' => $this->status,
            'is_paused' => $this->is_paused,
            'last_status_code' => $this->last_status_code,
            'last_latency_ms' => $this->last_latency_ms,
            'last_checked_at' => $this->last_checked_at,
            'last_failed_at' => $this->last_failed_at,
            'next_check_at' => $this->next_check_at,
            'notification_channel_ids' => $this->notificationChannels->modelKeys(),
            'team_ids' => $this->teams->modelKeys(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
