<?php

namespace App\Actions;

use App\Enums\MonitorStatus;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdateMonitor
{
    public function __construct(private SyncScopedRelation $syncScopedRelation) {}

    public function handle(User $user, Monitor $monitor, array $attributes): Monitor
    {
        return DB::transaction(function () use ($user, $monitor, $attributes): Monitor {
            $monitor = Monitor::query()->lockForUpdate()->findOrFail($monitor->id);
            $monitor->fill(Arr::except($attributes, ['notification_channel_ids', 'team_ids']));

            $urlChanged = $monitor->isDirty('url');
            $intervalChanged = $monitor->isDirty('interval_minutes');

            if ($urlChanged) {
                $this->resetCheckState($monitor);
            }

            if (($urlChanged || $intervalChanged) && ! $monitor->is_paused) {
                $monitor->next_check_at = now();
            }

            $monitor->save();

            if (array_key_exists('notification_channel_ids', $attributes)) {
                $this->syncScopedRelation->handle(
                    $monitor->notificationChannels(),
                    $attributes['notification_channel_ids'],
                    NotificationChannel::query()->visibleTo($user)->pluck('id')->all(),
                );
            }

            if (array_key_exists('team_ids', $attributes)) {
                $this->syncScopedRelation->handle($monitor->teams(), $attributes['team_ids'], $user->visibleTeamIds());
            }

            return $monitor;
        });
    }

    private function resetCheckState(Monitor $monitor): void
    {
        $monitor->forceFill([
            'status' => MonitorStatus::Pending,
            'consecutive_failures' => 0,
            'consecutive_successes' => 0,
            'last_status_code' => null,
            'last_latency_ms' => null,
            'last_checked_at' => null,
            'last_failed_at' => null,
        ]);

        $monitor->incidents()->whereNull('resolved_at')->update(['resolved_at' => now()]);
    }
}
