<?php

namespace App\Actions;

use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateMonitor
{
    public function handle(User $user, array $attributes): Monitor
    {
        return DB::transaction(function () use ($user, $attributes): Monitor {
            $channelIds = $attributes['notification_channel_ids']
                ?? NotificationChannel::query()->visibleTo($user)->where('is_default', true)->pluck('id')->all();

            $monitor = new Monitor([
                'interval_minutes' => config('pulsecheck.default_interval'),
                'timeout_seconds' => config('pulsecheck.timeout_default'),
                ...Arr::except($attributes, ['notification_channel_ids', 'team_ids']),
            ]);
            $monitor->next_check_at = now();
            $monitor->save();
            $monitor->notificationChannels()->sync($channelIds);
            $monitor->teams()->sync($attributes['team_ids'] ?? []);

            return $monitor;
        });
    }
}
