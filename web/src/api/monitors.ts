import { http } from './client.ts'
import type {
  CheckResult,
  CursorPaginatedResponse,
  DataResponse,
  Incident,
  LatencySeries,
  Monitor,
  MonitorInput,
  MonitorStats,
  PaginatedResponse,
  StatsPeriod,
  UptimeDay,
} from './types.ts'

export type MonitorStatusFilter = 'up' | 'degraded' | 'down' | 'pending' | 'paused'

export async function listMonitors(params: { page?: number; status?: MonitorStatusFilter } = {}) {
  const response = await http.get<PaginatedResponse<Monitor>>('/api/monitors', { params })

  return response.data
}

export async function getMonitor(id: number) {
  const response = await http.get<DataResponse<Monitor>>(`/api/monitors/${id}`)

  return response.data.data
}

export async function createMonitor(input: MonitorInput) {
  const response = await http.post<DataResponse<Monitor>>('/api/monitors', input)

  return response.data.data
}

export async function updateMonitor(id: number, input: Partial<MonitorInput>) {
  const response = await http.patch<DataResponse<Monitor>>(`/api/monitors/${id}`, input)

  return response.data.data
}

export async function deleteMonitor(id: number) {
  await http.delete(`/api/monitors/${id}`)
}

export async function pauseMonitor(id: number) {
  const response = await http.post<DataResponse<Monitor>>(`/api/monitors/${id}/pause`)

  return response.data.data
}

export async function resumeMonitor(id: number) {
  const response = await http.post<DataResponse<Monitor>>(`/api/monitors/${id}/resume`)

  return response.data.data
}

export async function getMonitorStats(id: number, period: StatsPeriod) {
  const response = await http.get<DataResponse<MonitorStats>>(`/api/monitors/${id}/stats`, { params: { period } })

  return response.data.data
}

export async function getLatencySeries(id: number, period: StatsPeriod) {
  const response = await http.get<DataResponse<LatencySeries>>(`/api/monitors/${id}/latency`, { params: { period } })

  return response.data.data
}

export async function getUptimeHistory(id: number, days = 30) {
  const response = await http.get<DataResponse<UptimeDay[]>>(`/api/monitors/${id}/uptime-history`, { params: { days } })

  return response.data.data
}

export async function listChecks(id: number, params: { from?: string; to?: string; cursor?: string | null }) {
  const response = await http.get<CursorPaginatedResponse<CheckResult>>(`/api/monitors/${id}/checks`, {
    params: Object.fromEntries(Object.entries(params).filter(([, value]) => value)),
  })

  return response.data
}

export async function listMonitorIncidents(id: number, page = 1) {
  const response = await http.get<PaginatedResponse<Incident>>(`/api/monitors/${id}/incidents`, { params: { page } })

  return response.data
}
