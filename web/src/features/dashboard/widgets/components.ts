import type { ComponentType } from 'react'
import type { DashboardWidgetType } from '../../../api/types.ts'
import { DownMonitorsWidget } from './DownMonitorsWidget.tsx'
import { ErrorBreakdownWidget } from './ErrorBreakdownWidget.tsx'
import { IncidentTimelineWidget } from './IncidentTimelineWidget.tsx'
import { LatencyTrendWidget } from './LatencyTrendWidget.tsx'
import { MonitorLatencyWidget } from './MonitorLatencyWidget.tsx'
import { RecentIncidentsWidget } from './RecentIncidentsWidget.tsx'
import { SlowestMonitorsWidget } from './SlowestMonitorsWidget.tsx'
import { StatusDistributionWidget } from './StatusDistributionWidget.tsx'
import { StatusSummaryWidget } from './StatusSummaryWidget.tsx'
import type { WidgetProps } from './types.ts'
import { UptimeTrendWidget } from './UptimeTrendWidget.tsx'

export const widgetComponents: Record<DashboardWidgetType, ComponentType<WidgetProps>> = {
  status_summary: StatusSummaryWidget,
  status_distribution: StatusDistributionWidget,
  down_monitors: DownMonitorsWidget,
  recent_incidents: RecentIncidentsWidget,
  uptime_trend: UptimeTrendWidget,
  latency_trend: LatencyTrendWidget,
  slowest_monitors: SlowestMonitorsWidget,
  incident_timeline: IncidentTimelineWidget,
  error_breakdown: ErrorBreakdownWidget,
  monitor_latency: MonitorLatencyWidget,
}
