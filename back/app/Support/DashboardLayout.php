<?php

namespace App\Support;

use App\Enums\DashboardWidgetType;
use App\Enums\StatsPeriod;
use App\Models\User;

class DashboardLayout
{
    public const COLUMNS = 12;

    public const MAX_HEIGHT = 12;

    public const MAX_WIDGETS = 30;

    public static function for(User $user): array
    {
        return [
            'period' => ($user->dashboard_period ?? StatsPeriod::Day)->value,
            'widgets' => array_map(
                fn (array $widget) => [...$widget, 'options' => (object) ($widget['options'] ?? [])],
                $user->dashboard_layout ?? self::defaultWidgets(),
            ),
            'is_default' => $user->dashboard_layout === null,
        ];
    }

    public static function defaultWidgets(): array
    {
        return [
            self::widget('status-summary', DashboardWidgetType::StatusSummary, 0, 0, 12, 2),
            self::widget('uptime-trend', DashboardWidgetType::UptimeTrend, 0, 2, 8, 4),
            self::widget('status-distribution', DashboardWidgetType::StatusDistribution, 8, 2, 4, 4),
            self::widget('latency-trend', DashboardWidgetType::LatencyTrend, 0, 6, 6, 4),
            self::widget('slowest-monitors', DashboardWidgetType::SlowestMonitors, 6, 6, 6, 4),
            self::widget('down-monitors', DashboardWidgetType::DownMonitors, 0, 10, 6, 4),
            self::widget('recent-incidents', DashboardWidgetType::RecentIncidents, 6, 10, 6, 4),
        ];
    }

    private static function widget(string $id, DashboardWidgetType $type, int $x, int $y, int $w, int $h): array
    {
        return ['id' => $id, 'type' => $type->value, 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'options' => []];
    }
}
