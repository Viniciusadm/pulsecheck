import { vi } from 'vitest'
import * as dashboardApi from '../api/dashboard.ts'
import type { DashboardLayout } from '../api/types.ts'
import { makeDashboardLayout } from './fixtures.ts'

export function mockDashboardApi(layout: DashboardLayout = makeDashboardLayout()) {
  vi.mocked(dashboardApi.getDashboardLayout).mockResolvedValue(layout)
  vi.mocked(dashboardApi.listRecentIncidents).mockResolvedValue([])
  vi.mocked(dashboardApi.getUptimeTrend).mockResolvedValue([])
  vi.mocked(dashboardApi.getLatencyTrend).mockResolvedValue([])
  vi.mocked(dashboardApi.getSlowestMonitors).mockResolvedValue([])
  vi.mocked(dashboardApi.getIncidentTimeline).mockResolvedValue([])
  vi.mocked(dashboardApi.getErrorBreakdown).mockResolvedValue([])
}
