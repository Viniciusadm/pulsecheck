<?php

namespace App\Services;

use App\Enums\IncidentKind;
use App\Enums\MonitorStatus;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\StatusPage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PublicStatusPagePresenter
{
    private const CACHE_SECONDS = 60;

    private const INCIDENT_DAYS = 14;

    private const MAX_INCIDENTS = 20;

    public function __construct(private UptimeCalculator $uptimeCalculator) {}

    public function present(StatusPage $statusPage): array
    {
        return Cache::remember($statusPage->cacheKey(), self::CACHE_SECONDS, fn (): array => $this->build($statusPage));
    }

    private function build(StatusPage $statusPage): array
    {
        $monitors = $statusPage->monitors()->get()->values();
        $keys = $monitors->mapWithKeys(fn (Monitor $monitor, int $index) => [$monitor->id => $index + 1]);

        $entries = $monitors->map(fn (Monitor $monitor) => $this->monitorEntry($statusPage, $monitor, $keys[$monitor->id]));

        return [
            'name' => $statusPage->name,
            'description' => $statusPage->description,
            'generated_at' => now()->toIso8601ZuluString(),
            'uptime_days' => $statusPage->uptime_days,
            'overall_status' => $this->overallStatus($entries),
            'monitors' => $entries->all(),
            'incidents' => $statusPage->show_incidents ? $this->incidents($monitors, $keys) : [],
        ];
    }

    private function monitorEntry(StatusPage $statusPage, Monitor $monitor, int $key): array
    {
        $days = $this->uptimeCalculator->daily($monitor, $statusPage->uptime_days);
        $totalChecks = array_sum(array_column($days, 'total_checks'));
        $successfulChecks = array_sum(array_column($days, 'successful_checks'));

        return [
            'key' => $key,
            'name' => $monitor->pivot->display_name ?: $monitor->name,
            'status' => self::publicStatus($monitor),
            'uptime_percentage' => UptimeCalculator::percentage($successfulChecks, $totalChecks),
            'latency_ms' => $statusPage->show_latency ? $this->uptimeCalculator->stats($monitor, now()->subDay(), now())['average_latency_ms'] : null,
            'days' => array_map(fn (array $day) => ['date' => $day['date'], 'uptime_percentage' => $day['uptime_percentage']], $days),
        ];
    }

    public static function publicStatus(Monitor $monitor): string
    {
        return match (true) {
            $monitor->is_paused => 'paused',
            $monitor->isInMaintenance() => 'maintenance',
            default => $monitor->status->value,
        };
    }

    private function overallStatus(Collection $entries): string
    {
        $statuses = $entries->pluck('status')->reject(fn (string $status) => in_array($status, ['paused', MonitorStatus::Pending->value], true));

        return match (true) {
            $statuses->isEmpty() => 'unknown',
            $statuses->every(fn (string $status) => $status === MonitorStatus::Down->value) => 'major_outage',
            $statuses->contains(MonitorStatus::Down->value) => 'partial_outage',
            $statuses->contains(MonitorStatus::Degraded->value) => 'degraded',
            $statuses->contains('maintenance') => 'maintenance',
            default => 'operational',
        };
    }

    private function incidents(Collection $monitors, Collection $keys): array
    {
        return Incident::query()
            ->whereIn('monitor_id', $monitors->modelKeys())
            ->where('kind', IncidentKind::Outage)
            ->where('started_at', '>=', now()->subDays(self::INCIDENT_DAYS))
            ->orderByDesc('started_at')
            ->limit(self::MAX_INCIDENTS)
            ->get(['id', 'monitor_id', 'started_at', 'resolved_at'])
            ->map(fn (Incident $incident) => [
                'monitor_key' => $keys[$incident->monitor_id],
                'started_at' => $incident->started_at->toIso8601ZuluString(),
                'resolved_at' => $incident->resolved_at?->toIso8601ZuluString(),
                'duration_seconds' => $incident->duration_seconds,
            ])
            ->all();
    }
}
