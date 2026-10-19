<?php

namespace App\Services;

use App\Models\Monitor;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UptimeCalculator
{
    public function stats(Monitor $monitor, CarbonInterface $from, CarbonInterface $to): array
    {
        $row = $monitor->checkResults()
            ->where('started_at', '>=', $from)
            ->where('started_at', '<', $to)
            ->where('in_maintenance', false)
            ->toBase()
            ->selectRaw('count(*) as total_checks')
            ->selectRaw('count(*) filter (where is_success) as successful_checks')
            ->selectRaw('count(*) filter (where is_degraded) as degraded_checks')
            ->selectRaw('avg(latency_ms) filter (where is_success) as average_latency_ms')
            ->selectRaw('percentile_cont(0.95) within group (order by latency_ms) filter (where is_success and latency_ms is not null) as p95_latency_ms')
            ->first();

        $totalChecks = (int) $row->total_checks;
        $successfulChecks = (int) $row->successful_checks;

        return [
            'total_checks' => $totalChecks,
            'successful_checks' => $successfulChecks,
            'degraded_checks' => (int) $row->degraded_checks,
            'uptime_percentage' => self::percentage($successfulChecks, $totalChecks),
            'average_latency_ms' => $row->average_latency_ms === null ? null : (int) round((float) $row->average_latency_ms),
            'p95_latency_ms' => $row->p95_latency_ms === null ? null : (int) round((float) $row->p95_latency_ms),
        ];
    }

    public function daily(Monitor $monitor, int $days): array
    {
        return $this->dailyForMonitors([$monitor->id], $days)[$monitor->id];
    }

    public function dailyForMonitors(array $monitorIds, int $days): array
    {
        if ($monitorIds === []) {
            return [];
        }

        $today = CarbonImmutable::now('UTC')->startOfDay();
        $firstDay = $today->subDays($days - 1);
        $rolledUp = DB::table('monitor_daily_uptimes')
            ->whereIn('monitor_id', $monitorIds)
            ->where('date', '>=', $firstDay->toDateString())
            ->select('monitor_id')
            ->selectRaw("to_char(date, 'YYYY-MM-DD') as day")
            ->addSelect(['total_checks', 'successful_checks', 'degraded_checks', 'maintenance_checks'])
            ->orderBy('date')
            ->get()
            ->groupBy('monitor_id')
            ->map(fn (Collection $rows) => $rows->keyBy('day'));

        $rawFromByMonitor = collect($monitorIds)->mapWithKeys(fn (int $monitorId) => [
            $monitorId => isset($rolledUp[$monitorId])
                ? CarbonImmutable::parse($rolledUp[$monitorId]->keys()->last(), 'UTC')->addDay()
                : $firstDay,
        ]);

        $raw = DB::table('check_results')
            ->where(function (Builder $query) use ($rawFromByMonitor): void {
                $rawFromByMonitor
                    ->groupBy(fn (CarbonImmutable $rawFrom) => $rawFrom->toIso8601String(), preserveKeys: true)
                    ->each(fn (Collection $group) => $query->orWhere(fn (Builder $range) => $range
                        ->whereIn('monitor_id', $group->keys()->all())
                        ->where('started_at', '>=', $group->first())));
            })
            ->select('monitor_id')
            ->selectRaw("to_char(started_at at time zone 'UTC', 'YYYY-MM-DD') as day")
            ->selectRaw('count(*) filter (where not in_maintenance) as total_checks')
            ->selectRaw('count(*) filter (where is_success and not in_maintenance) as successful_checks')
            ->selectRaw('count(*) filter (where is_degraded and not in_maintenance) as degraded_checks')
            ->selectRaw('count(*) filter (where in_maintenance) as maintenance_checks')
            ->groupBy('monitor_id', 'day')
            ->get()
            ->groupBy('monitor_id')
            ->map(fn (Collection $rows) => $rows->keyBy('day'));

        return collect($monitorIds)
            ->mapWithKeys(fn (int $monitorId) => [
                $monitorId => $this->dailyRows($firstDay, $days, ($rolledUp[$monitorId] ?? collect())->union($raw[$monitorId] ?? collect())),
            ])
            ->all();
    }

    public function averageLatencies(array $monitorIds, CarbonInterface $from, CarbonInterface $to): array
    {
        return DB::table('check_results')
            ->whereIn('monitor_id', $monitorIds)
            ->where('started_at', '>=', $from)
            ->where('started_at', '<', $to)
            ->where('in_maintenance', false)
            ->groupBy('monitor_id')
            ->select('monitor_id')
            ->selectRaw('avg(latency_ms) filter (where is_success) as average_latency_ms')
            ->pluck('average_latency_ms', 'monitor_id')
            ->map(fn (mixed $average) => $average === null ? null : (int) round((float) $average))
            ->all();
    }

    private function dailyRows(CarbonImmutable $firstDay, int $days, Collection $rows): array
    {
        return collect(range(0, $days - 1))
            ->map(function (int $offset) use ($firstDay, $rows): array {
                $day = $firstDay->addDays($offset)->toDateString();
                $totalChecks = (int) ($rows[$day]->total_checks ?? 0);
                $successfulChecks = (int) ($rows[$day]->successful_checks ?? 0);

                return [
                    'date' => $day,
                    'total_checks' => $totalChecks,
                    'successful_checks' => $successfulChecks,
                    'degraded_checks' => (int) ($rows[$day]->degraded_checks ?? 0),
                    'maintenance_checks' => (int) ($rows[$day]->maintenance_checks ?? 0),
                    'uptime_percentage' => self::percentage($successfulChecks, $totalChecks),
                ];
            })
            ->all();
    }

    public static function percentage(int $successfulChecks, int $totalChecks): ?float
    {
        if ($totalChecks === 0) {
            return null;
        }

        $percentage = round($successfulChecks / $totalChecks * 100, 2);

        return $successfulChecks < $totalChecks ? min($percentage, 99.99) : $percentage;
    }
}
