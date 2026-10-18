<?php

namespace App\Actions;

use App\Models\Monitor;
use Illuminate\Support\Facades\DB;

class ResumeMonitor
{
    public function handle(Monitor $monitor): Monitor
    {
        return DB::transaction(function () use ($monitor): Monitor {
            $monitor = Monitor::query()->lockForUpdate()->findOrFail($monitor->id);

            if (! $monitor->is_paused) {
                return $monitor;
            }

            $monitor->forceFill([
                'is_paused' => false,
                'consecutive_failures' => 0,
                'consecutive_successes' => 0,
                'consecutive_degraded' => 0,
                'consecutive_healthy' => 0,
                'next_check_at' => now(),
            ])->save();

            return $monitor;
        });
    }
}
