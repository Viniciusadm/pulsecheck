<?php

namespace App\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RollUpDailyUptime
{
    public function handle(int $days): int
    {
        $to = CarbonImmutable::now('UTC')->startOfDay();
        $from = $to->subDays($days);

        return DB::affectingStatement(<<<'SQL'
            INSERT INTO monitor_daily_uptimes (monitor_id, date, total_checks, successful_checks, degraded_checks, maintenance_checks, latency_sum_ms, latency_samples)
            SELECT
                monitor_id,
                (started_at AT TIME ZONE 'UTC')::date,
                count(*) FILTER (WHERE NOT in_maintenance),
                count(*) FILTER (WHERE is_success AND NOT in_maintenance),
                count(*) FILTER (WHERE is_degraded AND NOT in_maintenance),
                count(*) FILTER (WHERE in_maintenance),
                coalesce(sum(latency_ms) FILTER (WHERE is_success AND NOT in_maintenance), 0),
                count(latency_ms) FILTER (WHERE is_success AND NOT in_maintenance)
            FROM check_results
            WHERE started_at >= ? AND started_at < ?
            GROUP BY 1, 2
            ON CONFLICT (monitor_id, date) DO UPDATE SET
                total_checks = excluded.total_checks,
                successful_checks = excluded.successful_checks,
                degraded_checks = excluded.degraded_checks,
                maintenance_checks = excluded.maintenance_checks,
                latency_sum_ms = excluded.latency_sum_ms,
                latency_samples = excluded.latency_samples
        SQL, [$from, $to]);
    }
}
