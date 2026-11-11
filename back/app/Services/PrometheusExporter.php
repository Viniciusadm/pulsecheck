<?php

namespace App\Services;

use App\Enums\MonitorStatus;
use App\Models\CheckResult;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Support\Collection;

class PrometheusExporter
{
    private const UPTIME_WINDOW_HOURS = 24;

    public function render(User $user): string
    {
        $monitors = Monitor::query()->visibleTo($user)->orderBy('id')->get();
        $monitorIds = $monitors->modelKeys();
        $uptime = $this->uptimeByMonitor($monitorIds);
        $openIncidents = Incident::query()->whereIn('monitor_id', $monitorIds)->whereNull('resolved_at')->pluck('monitor_id')->flip();

        $metrics = [
            $this->family('pulsecheck_monitor_up', 'gauge', '1 when the monitor is up or degraded, 0 when it is down. Absent while pending or paused.', $monitors
                ->filter(fn (Monitor $monitor) => ! $monitor->is_paused && $monitor->status !== MonitorStatus::Pending)
                ->map(fn (Monitor $monitor) => [$this->labels($monitor), $monitor->status === MonitorStatus::Down ? 0 : 1])),
            $this->family('pulsecheck_monitor_status', 'gauge', 'Current status of the monitor, one series per possible status.', $monitors->flatMap(
                fn (Monitor $monitor) => collect(['up', 'degraded', 'down', 'pending', 'paused', 'maintenance'])
                    ->map(fn (string $status) => [[...$this->labels($monitor), 'status' => $status], (int) ($this->displayStatus($monitor) === $status)]),
            )),
            $this->family('pulsecheck_monitor_response_time_seconds', 'gauge', 'Response time of the last check.', $monitors
                ->filter(fn (Monitor $monitor) => $monitor->last_latency_ms !== null)
                ->map(fn (Monitor $monitor) => [$this->labels($monitor), $monitor->last_latency_ms / 1000])),
            $this->family('pulsecheck_monitor_last_check_timestamp_seconds', 'gauge', 'Unix time of the last check.', $monitors
                ->filter(fn (Monitor $monitor) => $monitor->last_checked_at !== null)
                ->map(fn (Monitor $monitor) => [$this->labels($monitor), $monitor->last_checked_at->getTimestamp()])),
            $this->family('pulsecheck_monitor_uptime_ratio', 'gauge', 'Share of successful checks in the last 24 hours, maintenance excluded.', $monitors
                ->filter(fn (Monitor $monitor) => isset($uptime[$monitor->id]))
                ->map(fn (Monitor $monitor) => [[...$this->labels($monitor), 'window' => '24h'], $uptime[$monitor->id]])),
            $this->family('pulsecheck_monitor_certificate_expiry_timestamp_seconds', 'gauge', 'Unix time when the TLS certificate expires.', $monitors
                ->filter(fn (Monitor $monitor) => $monitor->certificate_expires_at !== null)
                ->map(fn (Monitor $monitor) => [$this->labels($monitor), $monitor->certificate_expires_at->getTimestamp()])),
            $this->family('pulsecheck_monitor_open_incident', 'gauge', 'Whether the monitor has an open incident.', $monitors
                ->map(fn (Monitor $monitor) => [$this->labels($monitor), (int) $openIncidents->has($monitor->id)])),
            $this->family('pulsecheck_monitors', 'gauge', 'Number of monitors visible to the token owner.', collect([[[], $monitors->count()]])),
        ];

        return implode('', $metrics);
    }

    private function uptimeByMonitor(array $monitorIds): array
    {
        if ($monitorIds === []) {
            return [];
        }

        return CheckResult::query()
            ->whereIn('monitor_id', $monitorIds)
            ->where('started_at', '>=', now()->subHours(self::UPTIME_WINDOW_HOURS))
            ->where('in_maintenance', false)
            ->groupBy('monitor_id')
            ->selectRaw('monitor_id, count(*) as total, sum(case when is_success then 1 else 0 end) as successful')
            ->get()
            ->filter(fn (CheckResult $row) => (int) $row->getAttribute('total') > 0)
            ->mapWithKeys(fn (CheckResult $row) => [$row->monitor_id => round((int) $row->getAttribute('successful') / (int) $row->getAttribute('total'), 6)])
            ->all();
    }

    private function displayStatus(Monitor $monitor): string
    {
        return match (true) {
            $monitor->is_paused => 'paused',
            $monitor->isInMaintenance() => 'maintenance',
            default => $monitor->status->value,
        };
    }

    private function labels(Monitor $monitor): array
    {
        return ['id' => (string) $monitor->id, 'name' => $monitor->name, 'type' => $monitor->type->value];
    }

    private function family(string $name, string $type, string $help, Collection $samples): string
    {
        $lines = ["# HELP {$name} {$help}", "# TYPE {$name} {$type}"];

        foreach ($samples as [$labels, $value]) {
            $lines[] = $name.$this->formatLabels($labels).' '.$this->formatValue($value);
        }

        return implode("\n", $lines)."\n";
    }

    private function formatLabels(array $labels): string
    {
        if ($labels === []) {
            return '';
        }

        $pairs = array_map(
            fn (string $key, string $value) => $key.'="'.str_replace(['\\', '"', "\n"], ['\\\\', '\\"', '\\n'], $value).'"',
            array_keys($labels),
            $labels,
        );

        return '{'.implode(',', $pairs).'}';
    }

    private function formatValue(int|float $value): string
    {
        return is_int($value) ? (string) $value : rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
    }
}
