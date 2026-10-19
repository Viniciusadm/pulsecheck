<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;

class HourlyStats
{
    public const COLUMNS = ['monitor_id', 'hour', 'checks', 'successful_checks', 'latency_sum_ms', 'latency_samples', 'p95_latency_ms'];

    public static function aggregate(Builder $checkResults): Builder
    {
        return $checkResults
            ->where('in_maintenance', false)
            ->select('monitor_id')
            ->selectRaw("date_bin('1 hour'::interval, started_at, timestamptz '1970-01-01 00:00:00+00') as hour")
            ->selectRaw('count(*) as checks')
            ->selectRaw('count(*) filter (where is_success) as successful_checks')
            ->selectRaw('coalesce(sum(latency_ms) filter (where is_success), 0) as latency_sum_ms')
            ->selectRaw('count(latency_ms) filter (where is_success) as latency_samples')
            ->selectRaw('round(percentile_cont(0.95) within group (order by latency_ms) filter (where is_success and latency_ms is not null))::integer as p95_latency_ms')
            ->groupBy('monitor_id', 'hour');
    }
}
