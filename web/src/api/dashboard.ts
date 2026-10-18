import { http } from './client.ts'
import type {
  DashboardLayout,
  DashboardLayoutInput,
  DashboardSummary,
  DataResponse,
  ErrorBreakdownItem,
  Incident,
  IncidentTimelineBucket,
  LatencyTrendBucket,
  SlowMonitor,
  StatsPeriod,
  UptimeTrendBucket,
} from './types.ts'

export async function getDashboardSummary() {
  const response = await http.get<DataResponse<DashboardSummary>>('/api/dashboard/summary')

  return response.data.data
}

export async function listRecentIncidents(limit = 5) {
  const response = await http.get<DataResponse<Incident[]>>('/api/incidents', { params: { limit } })

  return response.data.data
}

export async function getDashboardLayout() {
  const response = await http.get<DataResponse<DashboardLayout>>('/api/me/dashboard')

  return response.data.data
}

export async function updateDashboardLayout(input: DashboardLayoutInput) {
  const response = await http.put<DataResponse<DashboardLayout>>('/api/me/dashboard', input)

  return response.data.data
}

export async function resetDashboardLayout() {
  const response = await http.delete<DataResponse<DashboardLayout>>('/api/me/dashboard')

  return response.data.data
}

async function getMetric<T>(path: string, period: StatsPeriod) {
  const response = await http.get<DataResponse<T>>(`/api/dashboard/${path}`, { params: { period } })

  return response.data.data
}

export function getUptimeTrend(period: StatsPeriod) {
  return getMetric<UptimeTrendBucket[]>('uptime-trend', period)
}

export function getLatencyTrend(period: StatsPeriod) {
  return getMetric<LatencyTrendBucket[]>('latency-trend', period)
}

export function getSlowestMonitors(period: StatsPeriod) {
  return getMetric<SlowMonitor[]>('slowest-monitors', period)
}

export function getIncidentTimeline(period: StatsPeriod) {
  return getMetric<IncidentTimelineBucket[]>('incident-timeline', period)
}

export function getErrorBreakdown(period: StatsPeriod) {
  return getMetric<ErrorBreakdownItem[]>('error-breakdown', period)
}
