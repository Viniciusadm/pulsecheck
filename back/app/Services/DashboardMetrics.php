<?php

namespace App\Services;

use App\Enums\IncidentKind;
use App\Enums\StatsPeriod;
use App\Models\Monitor;
use App\Models\User;
use App\Support\BucketWindow;
use App\Support\HourlyStats;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardMetrics
{
    private const CACHE_SECONDS = 15;

    public function uptimeTrend(User $user, StatsPeriod $period): array
    {
        return $this->remember($user, __FUNCTION__, $period, function () use ($user, $period): array {
            $window = new BucketWindow($period);

            $rows = $this->usesHourlyStats($period)
                ? $window->selectBucket($this->hourlyStats($user, $window), 'hour')
                    ->selectRaw('sum(checks) as checks')
                    ->selectRaw('sum(successful_checks) as successful_checks')
                    ->get()
                : $window->selectBucket($this->checkResults($user), 'started_at')
                    ->selectRaw('count(*) as checks')
                    ->selectRaw('count(*) filter (where is_success) as successful_checks')
                    ->get();

            return $window->fill($rows, fn (?object $row) => [
                'checks' => (int) ($row->checks ?? 0),
                'failed_checks' => (int) ($row->checks ?? 0) - (int) ($row->successful_checks ?? 0),
                'uptime_percentage' => UptimeCalculator::percentage((int) ($row->successful_checks ?? 0), (int) ($row->checks ?? 0)),
            ]);
        });
    }

    public function latencyTrend(User $user, StatsPeriod $period): array
    {
        return $this->remember($user, __FUNCTION__, $period, function () use ($user, $period): array {
            $window = new BucketWindow($period);

            $rows = $this->usesHourlyStats($period)
                ? $window->selectBucket($this->hourlyStats($user, $window), 'hour')
                    ->selectRaw('sum(latency_sum_ms)::numeric / nullif(sum(latency_samples), 0) as average_latency_ms')
                    ->selectRaw('sum(p95_latency_ms * latency_samples)::numeric / nullif(sum(latency_samples), 0) as p95_latency_ms')
                    ->get()
                : $window->selectBucket($this->checkResults($user), 'started_at')
                    ->selectRaw('avg(latency_ms) filter (where is_success) as average_latency_ms')
                    ->selectRaw('percentile_cont(0.95) within group (order by latency_ms) filter (where is_success and latency_ms is not null) as p95_latency_ms')
                    ->get();

            return $window->fill($rows, fn (?object $row) => [
                'average_latency_ms' => $this->roundedOrNull($row?->average_latency_ms),
                'p95_latency_ms' => $this->roundedOrNull($row?->p95_latency_ms),
            ]);
        });
    }

    public function slowestMonitors(User $user, StatsPeriod $period, int $limit = 5): array
    {
        return $this->remember($user, __FUNCTION__.":{$limit}", $period, fn (): array => $this->slowestMonitorsQuery($user, $period)
            ->orderByDesc('average_latency_ms')
            ->orderBy('monitors.id')
            ->limit($limit)
            ->get()
            ->map(fn (object $row) => [
                'monitor_id' => (int) $row->id,
                'name' => $row->name,
                'average_latency_ms' => $this->roundedOrNull($row->average_latency_ms),
            ])
            ->all());
    }

    public function incidentTimeline(User $user, StatsPeriod $period): array
    {
        return $this->remember($user, __FUNCTION__, $period, function () use ($user, $period): array {
            $window = new BucketWindow($period);

            $incidents = DB::table('incidents')
                ->where('kind', IncidentKind::Outage->value)
                ->whereIn('monitor_id', $this->visibleMonitors($user)->select('monitors.id'));

            $rows = $window->selectBucket($incidents, 'started_at')
                ->selectRaw('count(*) as incidents')
                ->get();

            return $window->fill($rows, fn (?object $row) => [
                'incidents' => (int) ($row->incidents ?? 0),
            ]);
        });
    }

    public function errorBreakdown(User $user, StatsPeriod $period): array
    {
        return $this->remember($user, __FUNCTION__, $period, function () use ($user, $period): array {
            return $this->checkResults($user)
                ->where('started_at', '>=', (new BucketWindow($period))->first)
                ->where('is_success', false)
                ->selectRaw("coalesce(error_type, 'unknown') as type")
                ->selectRaw('count(*) as failed_checks')
                ->groupBy('type')
                ->orderByDesc('failed_checks')
                ->orderBy('type')
                ->get()
                ->map(fn (object $row) => [
                    'error_type' => $row->type,
                    'failed_checks' => (int) $row->failed_checks,
                ])
                ->all();
        });
    }

    private function slowestMonitorsQuery(User $user, StatsPeriod $period): QueryBuilder
    {
        $window = new BucketWindow($period);

        if ($this->usesHourlyStats($period)) {
            return $this->hourlyStats($user, $window)
                ->join('monitors', 'monitors.id', '=', 'hourly_stats.monitor_id')
                ->groupBy('monitors.id', 'monitors.name')
                ->havingRaw('sum(hourly_stats.latency_samples) > 0')
                ->select('monitors.id', 'monitors.name')
                ->selectRaw('sum(hourly_stats.latency_sum_ms)::numeric / sum(hourly_stats.latency_samples) as average_latency_ms');
        }

        return $this->visibleMonitors($user)
            ->toBase()
            ->join('check_results', 'check_results.monitor_id', '=', 'monitors.id')
            ->where('check_results.started_at', '>=', $window->first)
            ->where('check_results.is_success', true)
            ->where('check_results.in_maintenance', false)
            ->whereNotNull('check_results.latency_ms')
            ->groupBy('monitors.id', 'monitors.name')
            ->select('monitors.id', 'monitors.name')
            ->selectRaw('avg(check_results.latency_ms) as average_latency_ms');
    }

    private function usesHourlyStats(StatsPeriod $period): bool
    {
        return $period !== StatsPeriod::Day;
    }

    private function hourlyStats(User $user, BucketWindow $window): QueryBuilder
    {
        $rolledUpUntil = $this->rolledUpUntil($window);

        $rolledUp = DB::table('monitor_hourly_stats')
            ->select(HourlyStats::COLUMNS)
            ->whereIn('monitor_id', $this->visibleMonitors($user)->select('monitors.id'))
            ->where('hour', '>=', $window->first)
            ->where('hour', '<', $rolledUpUntil);

        $recent = HourlyStats::aggregate(DB::table('check_results')
            ->whereIn('monitor_id', $this->visibleMonitors($user)->select('monitors.id'))
            ->where('started_at', '>=', $rolledUpUntil)
            ->where('started_at', '<=', $window->now));

        return DB::query()->fromSub($rolledUp->unionAll($recent), 'hourly_stats');
    }

    private function rolledUpUntil(BucketWindow $window): CarbonImmutable
    {
        $latestHour = DB::table('monitor_hourly_stats')->where('hour', '>=', $window->first)->max('hour');

        return $latestHour === null ? $window->first : CarbonImmutable::parse($latestHour)->utc()->addHour();
    }

    private function remember(User $user, string $metric, StatsPeriod $period, callable $compute): array
    {
        $scope = $user->canViewEverything() ? 'all' : implode(',', $user->teams()->orderBy('teams.id')->pluck('teams.id')->all());

        return Cache::remember("dashboard-metrics:{$metric}:{$period->value}:{$scope}", self::CACHE_SECONDS, $compute);
    }

    private function visibleMonitors(User $user): Builder
    {
        return Monitor::query()->visibleTo($user);
    }

    private function checkResults(User $user): QueryBuilder
    {
        return DB::table('check_results')
            ->where('in_maintenance', false)
            ->whereIn('monitor_id', $this->visibleMonitors($user)->select('monitors.id'));
    }

    private function roundedOrNull(mixed $value): ?int
    {
        return $value === null ? null : (int) round((float) $value);
    }
}
