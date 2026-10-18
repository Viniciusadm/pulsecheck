<?php

namespace App\Actions;

use App\Models\Monitor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateMonitor
{
    public function handle(User $user, array $attributes): Monitor
    {
        return DB::transaction(function () use ($user, $attributes): Monitor {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $maxMonitors = (int) config('pulsecheck.max_monitors_per_user');

            if ($user->monitors()->count() >= $maxMonitors) {
                throw ValidationException::withMessages([
                    'monitors' => __('You have reached the limit of :max monitors.', ['max' => $maxMonitors]),
                ]);
            }

            $monitor = $user->monitors()->make([
                'interval_minutes' => config('pulsecheck.default_interval'),
                'timeout_seconds' => config('pulsecheck.timeout_default'),
                ...$attributes,
            ]);
            $monitor->next_check_at = now();
            $monitor->save();

            return $monitor;
        });
    }
}
