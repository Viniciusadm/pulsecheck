<?php

namespace App\Jobs;

use App\Actions\RecordCheckResult;
use App\Models\Monitor;
use App\Services\HttpChecker;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RunMonitorCheck implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    public int $uniqueFor;

    public int $monitorId;

    public function __construct(Monitor $monitor)
    {
        $this->monitorId = $monitor->id;
        $this->uniqueFor = $monitor->interval_minutes * 60;
        $this->timeout = $monitor->timeout_seconds + 30;
    }

    public function uniqueId(): string
    {
        return (string) $this->monitorId;
    }

    public function handle(HttpChecker $checker, RecordCheckResult $recordCheckResult): void
    {
        $monitor = Monitor::query()->find($this->monitorId);

        if ($monitor === null || $monitor->is_paused) {
            return;
        }

        $outcome = $checker->check($monitor);

        $recordCheckResult->handle($monitor, $outcome);

        if (! $outcome->isSuccess) {
            Log::info('Monitor check failed.', [
                'monitor_id' => $monitor->id,
                'error_type' => $outcome->errorType?->value,
            ]);
        }
    }
}
