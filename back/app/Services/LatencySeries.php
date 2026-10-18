<?php

namespace App\Services;

use App\Enums\StatsPeriod;
use App\Models\Monitor;
use Carbon\CarbonImmutable;

class LatencySeries
{
    public function buckets(Monitor $monitor, StatsPeriod $period): array
    {
        $bucketSeconds = $period->bucketMinutes() * 60;
        $now = CarbonImmutable::now('UTC');
        $firstBucket = $this->floor($now->subHours($period->hours()), $bucketSeconds);
        $lastBucket = $this->floor($now, $bucketSeconds);

        $rows = $monitor->checkResults()
            ->where('started_at', '>=', $firstBucket)
            ->where('started_at', '<=', $now)
            ->toBase()
            ->selectRaw("extract(epoch from date_bin(?::interval, started_at, timestamptz '1970-01-01 00:00:00+00'))::bigint as bucket", ["{$period->bucketMinutes()} minutes"])
            ->selectRaw('avg(latency_ms) filter (where is_success) as average_latency_ms')
            ->selectRaw('count(*) as checks')
            ->selectRaw('count(*) filter (where not is_success) as failed_checks')
            ->groupBy('bucket')
            ->get()
            ->keyBy('bucket');

        $buckets = [];

        for ($bucket = $firstBucket; $bucket->lessThanOrEqualTo($lastBucket); $bucket = $bucket->addSeconds($bucketSeconds)) {
            $row = $rows[$bucket->getTimestamp()] ?? null;

            $buckets[] = [
                'bucket_start' => $bucket->toIso8601ZuluString(),
                'average_latency_ms' => $row?->average_latency_ms === null ? null : (int) round((float) $row->average_latency_ms),
                'checks' => (int) ($row->checks ?? 0),
                'failed_checks' => (int) ($row->failed_checks ?? 0),
            ];
        }

        return $buckets;
    }

    private function floor(CarbonImmutable $moment, int $bucketSeconds): CarbonImmutable
    {
        $timestamp = $moment->getTimestamp();

        return CarbonImmutable::createFromTimestampUTC($timestamp - ($timestamp % $bucketSeconds));
    }
}
