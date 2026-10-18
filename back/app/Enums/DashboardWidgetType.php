<?php

namespace App\Enums;

enum DashboardWidgetType: string
{
    case StatusSummary = 'status_summary';
    case StatusDistribution = 'status_distribution';
    case DownMonitors = 'down_monitors';
    case RecentIncidents = 'recent_incidents';
    case UptimeTrend = 'uptime_trend';
    case LatencyTrend = 'latency_trend';
    case SlowestMonitors = 'slowest_monitors';
    case IncidentTimeline = 'incident_timeline';
    case ErrorBreakdown = 'error_breakdown';
    case MonitorLatency = 'monitor_latency';

    public function minWidth(): int
    {
        return match ($this) {
            self::StatusDistribution, self::DownMonitors, self::RecentIncidents, self::ErrorBreakdown => 3,
            default => 4,
        };
    }

    public function minHeight(): int
    {
        return match ($this) {
            self::StatusSummary => 2,
            default => 3,
        };
    }

    public function requiresMonitor(): bool
    {
        return $this === self::MonitorLatency;
    }
}
