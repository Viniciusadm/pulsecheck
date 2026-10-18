<?php

namespace App\Jobs;

use App\Actions\RecordCheckResult;
use App\Models\InstanceSettings;
use App\Models\Monitor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\App;
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

    public function handle(RecordCheckResult $recordCheckResult): void
    {
        $monitor = Monitor::query()->find($this->monitorId);

        if ($monitor === null || $monitor->is_paused) {
            return;
        }

        App::setLocale(InstanceSettings::currentLocale());

        $outcome = $monitor->type->checker()->check($monitor)?->withLatencyThreshold($monitor->latency_threshold_ms);

        if ($outcome === null) {
            return;
        }

        $recordCheckResult->handle($monitor, $outcome);

        if (! $outcome->isSuccess) {
            Log::info('Monitor check failed.', [
                'monitor_id' => $monitor->id,
                'error_type' => $outcome->errorType?->value,
            ]);
        }
    }
}
