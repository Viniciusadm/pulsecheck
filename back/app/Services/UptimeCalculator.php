<?php

namespace App\Services;

use App\Models\Monitor;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class UptimeCalculator
{
    public function stats(Monitor $monitor, CarbonInterface $from, CarbonInterface $to): array
    {
        $row = $monitor->checkResults()
            ->where('started_at', '>=', $from)
            ->where('started_at', '<', $to)
            ->toBase()
            ->selectRaw('count(*) as total_checks')
            ->selectRaw('count(*) filter (where is_success) as successful_checks')
            ->selectRaw('avg(latency_ms) filter (where is_success) as average_latency_ms')
            ->selectRaw('percentile_cont(0.95) within group (order by latency_ms) filter (where is_success and latency_ms is not null) as p95_latency_ms')
            ->first();

        $totalChecks = (int) $row->total_checks;
        $successfulChecks = (int) $row->successful_checks;

        return [
            'total_checks' => $totalChecks,
            'successful_checks' => $successfulChecks,
            'uptime_percentage' => $this->percentage($successfulChecks, $totalChecks),
            'average_latency_ms' => $row->average_latency_ms === null ? null : (int) round((float) $row->average_latency_ms),
            'p95_latency_ms' => $row->p95_latency_ms === null ? null : (int) round((float) $row->p95_latency_ms),
        ];
    }

    public function daily(Monitor $monitor, int $days): array
    {
        $firstDay = CarbonImmutable::now('UTC')->startOfDay()->subDays($days - 1);

        $rows = $monitor->checkResults()
            ->where('started_at', '>=', $firstDay)
            ->toBase()
            ->selectRaw("to_char(started_at at time zone 'UTC', 'YYYY-MM-DD') as day")
            ->selectRaw('count(*) as total_checks')
            ->selectRaw('count(*) filter (where is_success) as successful_checks')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        return collect(range(0, $days - 1))
            ->map(function (int $offset) use ($firstDay, $rows): array {
                $day = $firstDay->addDays($offset)->toDateString();
                $totalChecks = (int) ($rows[$day]->total_checks ?? 0);
                $successfulChecks = (int) ($rows[$day]->successful_checks ?? 0);

                return [
                    'date' => $day,
                    'total_checks' => $totalChecks,
                    'successful_checks' => $successfulChecks,
                    'uptime_percentage' => $this->percentage($successfulChecks, $totalChecks),
                ];
            })
            ->all();
    }

    private function percentage(int $successfulChecks, int $totalChecks): ?float
    {
        if ($totalChecks === 0) {
            return null;
        }

        $percentage = round($successfulChecks / $totalChecks * 100, 2);

        return $successfulChecks < $totalChecks ? min($percentage, 99.99) : $percentage;
    }
}
