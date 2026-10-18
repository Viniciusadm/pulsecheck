<?php

namespace App\Listeners;

use App\Jobs\RunMonitorCheck;
use Illuminate\Queue\Events\UniqueJobSkipped;
use Illuminate\Support\Facades\Log;

class LogSkippedMonitorCheck
{
    public function handle(UniqueJobSkipped $event): void
    {
        if (! $event->job instanceof RunMonitorCheck) {
            return;
        }

        Log::warning('Monitor check skipped because the previous one is still queued or running.', ['monitor_id' => $event->job->monitorId]);
    }
}
