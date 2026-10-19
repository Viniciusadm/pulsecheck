<?php

namespace App\Actions;

use App\Models\MonitorHourlyStat;
use App\Support\HourlyStats;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RollUpHourlyStats
{
    public function handle(int $hours): int
    {
        $to = CarbonImmutable::now('UTC')->startOfHour();
        $from = $to->subHours($hours);
        $latestHour = MonitorHourlyStat::query()->max('hour');

        if ($latestHour !== null) {
            $catchUpFrom = CarbonImmutable::parse($latestHour)->utc()->addHour()->max($to->subDays((int) config('pulsecheck.history_retention_days')));
            $from = $from->min($catchUpFrom);
        }

        $stats = HourlyStats::aggregate(DB::table('check_results')
            ->where('started_at', '>=', $from)
            ->where('started_at', '<', $to));
        $columns = implode(', ', HourlyStats::COLUMNS);

        return DB::affectingStatement(<<<SQL
            INSERT INTO monitor_hourly_stats ({$columns})
            {$stats->toSql()}
            ON CONFLICT (monitor_id, hour) DO UPDATE SET
                checks = excluded.checks,
                successful_checks = excluded.successful_checks,
                latency_sum_ms = excluded.latency_sum_ms,
                latency_samples = excluded.latency_samples,
                p95_latency_ms = excluded.p95_latency_ms
        SQL, $stats->getBindings());
    }
}
