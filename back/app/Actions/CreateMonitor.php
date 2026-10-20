<?php

namespace App\Actions;

use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateMonitor
{
    public function handle(User $user, array $attributes): Monitor
    {
        return DB::transaction(function () use ($user, $attributes): Monitor {
            $teamIds = $attributes['team_ids'] ?? [];
            $channelIds = $attributes['notification_channel_ids'] ?? NotificationChannel::query()
                ->visibleTo($user)
                ->where('is_default', true)
                ->when($teamIds !== [], fn (Builder $channels) => $channels->where(fn (Builder $scoped) => $scoped
                    ->doesntHave('teams')
                    ->orWhereHas('teams', fn (Builder $teams) => $teams->whereIn('teams.id', $teamIds))))
                ->pluck('id')
                ->all();

            $monitor = new Monitor([
                'interval_minutes' => config('pulsecheck.default_interval'),
                'timeout_seconds' => config('pulsecheck.timeout_default'),
                ...Arr::except($attributes, ['notification_channel_ids', 'team_ids']),
            ]);
            $monitor->next_check_at = $monitor->initialCheckAt();

            if ($monitor->type->isPassive()) {
                $monitor->heartbeat_token = Str::random(40);
            }

            $monitor->save();
            $monitor->notificationChannels()->sync($channelIds);
            $monitor->teams()->sync($attributes['team_ids'] ?? []);

            return $monitor;
        });
    }
}
