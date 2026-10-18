import type { DashboardWidgetType } from '../../../api/types.ts'
import type { MessageKey } from '../../../i18n/index.ts'

type Size = { w: number; h: number }

export type WidgetDefinition = {
  title: MessageKey
  description: MessageKey
  defaultSize: Size
  minSize: Size
  requiresMonitor: boolean
  fitsContent: boolean
}

const standardMinimum: Size = { w: 4, h: 3 }

const compactMinimum: Size = { w: 3, h: 3 }

export const widgetDefinitions: Record<DashboardWidgetType, WidgetDefinition> = {
  status_summary: {
    title: 'dashboard.widgets.status_summary.title',
    description: 'dashboard.widgets.status_summary.description',
    defaultSize: { w: 12, h: 2 },
    minSize: { w: 4, h: 2 },
    fitsContent: true,
    requiresMonitor: false,
  },
  status_distribution: {
    title: 'dashboard.widgets.status_distribution.title',
    description: 'dashboard.widgets.status_distribution.description',
    defaultSize: { w: 4, h: 4 },
    minSize: compactMinimum,
    fitsContent: false,
    requiresMonitor: false,
  },
  down_monitors: {
    title: 'dashboard.attentionHeading',
    description: 'dashboard.widgets.down_monitors.description',
    defaultSize: { w: 6, h: 4 },
    minSize: compactMinimum,
    fitsContent: true,
    requiresMonitor: false,
  },
  recent_incidents: {
    title: 'dashboard.incidentsHeading',
    description: 'dashboard.widgets.recent_incidents.description',
    defaultSize: { w: 6, h: 4 },
    minSize: compactMinimum,
    fitsContent: true,
    requiresMonitor: false,
  },
  uptime_trend: {
    title: 'dashboard.widgets.uptime_trend.title',
    description: 'dashboard.widgets.uptime_trend.description',
    defaultSize: { w: 8, h: 4 },
    minSize: standardMinimum,
    fitsContent: false,
    requiresMonitor: false,
  },
  latency_trend: {
    title: 'dashboard.widgets.latency_trend.title',
    description: 'dashboard.widgets.latency_trend.description',
    defaultSize: { w: 6, h: 4 },
    minSize: standardMinimum,
    fitsContent: false,
    requiresMonitor: false,
  },
  slowest_monitors: {
    title: 'dashboard.widgets.slowest_monitors.title',
    description: 'dashboard.widgets.slowest_monitors.description',
    defaultSize: { w: 6, h: 4 },
    minSize: standardMinimum,
    fitsContent: true,
    requiresMonitor: false,
  },
  incident_timeline: {
    title: 'dashboard.widgets.incident_timeline.title',
    description: 'dashboard.widgets.incident_timeline.description',
    defaultSize: { w: 6, h: 4 },
    minSize: standardMinimum,
    fitsContent: false,
    requiresMonitor: false,
  },
  error_breakdown: {
    title: 'dashboard.widgets.error_breakdown.title',
    description: 'dashboard.widgets.error_breakdown.description',
    defaultSize: { w: 4, h: 4 },
    minSize: compactMinimum,
    fitsContent: true,
    requiresMonitor: false,
  },
  monitor_latency: {
    title: 'dashboard.widgets.monitor_latency.title',
    description: 'dashboard.widgets.monitor_latency.description',
    defaultSize: { w: 6, h: 4 },
    minSize: standardMinimum,
    fitsContent: false,
    requiresMonitor: true,
  },
}

export const widgetTypes = Object.keys(widgetDefinitions) as DashboardWidgetType[]
