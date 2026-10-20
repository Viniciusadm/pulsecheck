<?php

namespace App\Http\Resources;

use App\Models\NotificationChannel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonitorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'url' => $this->url,
            'options' => (object) [...$this->type->checker()->defaultOptions(), ...($this->options ?? [])],
            'secrets_set' => array_values(array_filter($this->type->checker()->secretKeys(), fn (string $key) => ! empty($this->secret($key)) && ($key !== 'auth' || ($this->secret('auth')['type'] ?? 'none') !== 'none'))),
            'auth_type' => $this->secret('auth')['type'] ?? 'none',
            'interval_minutes' => $this->interval_minutes,
            'timeout_seconds' => $this->timeout_seconds,
            'latency_threshold_ms' => $this->latency_threshold_ms,
            'reminder_interval_minutes' => $this->reminder_interval_minutes,
            'display_target' => $this->displayTarget(),
            'certificate_expires_at' => $this->certificate_expires_at,
            'last_heartbeat_at' => $this->last_heartbeat_at,
            'badge_url' => $this->when(
                $request->user()?->can('update', $this->resource) ?? false,
                fn () => $this->badge_token === null ? null : url("/api/public/badges/{$this->badge_token}"),
            ),
            'heartbeat_url' => $this->when(
                $this->heartbeat_token !== null && $request->user()?->can('update', $this->resource),
                fn () => route('heartbeat', ['token' => $this->heartbeat_token]),
            ),
            'status' => $this->status,
            'is_paused' => $this->is_paused,
            'is_in_maintenance' => $this->isInMaintenance(),
            'maintenance_ends_at' => $this->maintenance_ends_at,
            'last_status_code' => $this->last_status_code,
            'last_latency_ms' => $this->last_latency_ms,
            'last_checked_at' => $this->last_checked_at,
            'last_failed_at' => $this->last_failed_at,
            'next_check_at' => $this->next_check_at,
            'notification_channel_ids' => $this->when($request->user() !== null, fn () => $this->visibleChannelIds($request->user())),
            'team_ids' => $this->when($request->user() !== null, fn () => $request->user()->scopedTeamIds($this->teams->modelKeys())),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function visibleChannelIds(User $user): array
    {
        if ($user->canViewEverything()) {
            return $this->notificationChannels->modelKeys();
        }

        $teamIds = $user->teams->modelKeys();

        return $this->notificationChannels
            ->filter(fn (NotificationChannel $channel) => array_intersect($channel->teams->modelKeys(), $teamIds) !== [])
            ->values()
            ->modelKeys();
    }
}
