<?php

namespace App\Services;

use App\Enums\StatsPeriod;
use App\Models\Monitor;
use App\Support\BucketWindow;

class LatencySeries
{
    public function buckets(Monitor $monitor, StatsPeriod $period): array
    {
        $window = new BucketWindow($period);

        $rows = $window->selectBucket($monitor->checkResults()->where('in_maintenance', false)->toBase(), 'started_at')
            ->selectRaw('avg(latency_ms) filter (where is_success) as average_latency_ms')
            ->selectRaw('count(*) as checks')
            ->selectRaw('count(*) filter (where not is_success) as failed_checks')
            ->get();

        return $window->fill($rows, fn (?object $row) => [
            'average_latency_ms' => $row?->average_latency_ms === null ? null : (int) round((float) $row->average_latency_ms),
            'checks' => (int) ($row->checks ?? 0),
            'failed_checks' => (int) ($row->failed_checks ?? 0),
        ]);
    }
}
