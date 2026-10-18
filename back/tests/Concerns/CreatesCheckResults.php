<?php

namespace Tests\Concerns;

use App\Enums\CheckErrorType;
use App\Models\CheckResult;
use App\Models\Monitor;
use Carbon\CarbonInterface;

trait CreatesCheckResults
{
    protected function checkAt(Monitor $monitor, CarbonInterface $startedAt, bool $isSuccess = true, ?int $latencyMs = 100): CheckResult
    {
        return CheckResult::factory()->for($monitor)->create([
            'started_at' => $startedAt,
            'finished_at' => $startedAt->copy()->addMilliseconds($latencyMs ?? 0),
            'is_success' => $isSuccess,
            'latency_ms' => $latencyMs,
            'status_code' => $isSuccess ? 200 : null,
            'error_type' => $isSuccess ? null : CheckErrorType::Timeout,
            'error_message' => $isSuccess ? null : 'Operation timed out',
        ]);
    }
}
