<?php

namespace App\Actions;

use App\Models\Monitor;
use Illuminate\Support\Facades\DB;

class PauseMonitor
{
    public function handle(Monitor $monitor): Monitor
    {
        return DB::transaction(function () use ($monitor): Monitor {
            $monitor = Monitor::query()->lockForUpdate()->findOrFail($monitor->id);

            $monitor->forceFill([
                'is_paused' => true,
                'next_check_at' => null,
            ])->save();

            return $monitor;
        });
    }
}
