<?php

namespace App\Actions;

use App\Models\Monitor;
use App\Models\NotificationChannel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateMonitor
{
    public function handle(array $attributes): Monitor
    {
        return DB::transaction(function () use ($attributes): Monitor {
            $channelIds = $attributes['notification_channel_ids']
                ?? NotificationChannel::query()->where('is_default', true)->pluck('id')->all();

            $monitor = new Monitor([
                'interval_minutes' => config('pulsecheck.default_interval'),
                'timeout_seconds' => config('pulsecheck.timeout_default'),
                ...Arr::except($attributes, 'notification_channel_ids'),
            ]);
            $monitor->next_check_at = now();
            $monitor->save();
            $monitor->notificationChannels()->sync($channelIds);

            return $monitor;
        });
    }
}
