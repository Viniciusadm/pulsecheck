import { screen, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as authApi from '../../api/auth.ts'
import * as configApi from '../../api/config.ts'
import * as dashboardApi from '../../api/dashboard.ts'
import * as monitorsApi from '../../api/monitors.ts'
import * as realtimeApi from '../../api/realtime.ts'
import type { Incident, Monitor, PaginatedResponse } from '../../api/types.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import { mockDashboardApi } from '../../test/dashboardMocks.ts'
import { ada, makeConfig, makeMonitor, openIncident, paginated } from '../../test/fixtures.ts'
import { renderApp } from '../../test/renderApp.tsx'

vi.mock('../../api/auth.ts')
vi.mock('../../api/dashboard.ts')
vi.mock('../../api/monitors.ts')
vi.mock('../../api/realtime.ts')

type Listener = (event: { monitor: Omit<Monitor, 'notification_channel_ids'> } | { incident: Incident }) => void

function fakeEcho() {
  const listeners = new Map<string, Listener>()
  const channel = {
    listen: (event: string, listener: Listener) => {
      listeners.set(event, listener)

      return channel
    },
  }
  const privateChannel = vi.fn(() => channel)
  const disconnect = vi.fn()
  const connector = { onConnectionChange: vi.fn(() => () => {}) }

  vi.mocked(realtimeApi.createEcho).mockReturnValue({ private: privateChannel, disconnect, connector } as unknown as ReturnType<typeof realtimeApi.createEcho>)

  return { listeners, privateChannel, disconnect }
}

describe('useMonitorRealtime', () => {
  beforeEach(() => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(ada)
    vi.mocked(dashboardApi.getDashboardSummary).mockResolvedValue({ total: 1, up: 1, degraded: 0, down: 0, pending: 0, maintenance: 0, paused: 0 })
    mockDashboardApi()
    vi.mocked(monitorsApi.listMonitors).mockResolvedValue(paginated([makeMonitor()]))
  })

  it('subscribes to the current user monitors channel and refreshes monitors when a check arrives', async () => {
    const echo = fakeEcho()
    vi.mocked(configApi.getAppConfig).mockResolvedValue(makeConfig({ realtime: { key: 'public-key' } }))

    const { queryClient } = renderApp('/dashboard')

    expect(await screen.findAllByRole('link', { name: 'Shop API' })).not.toHaveLength(0)
    await waitFor(() => expect(echo.listeners.has('.monitor.checked')).toBe(true))
    expect(realtimeApi.createEcho).toHaveBeenCalledWith('public-key')
    expect(echo.privateChannel).toHaveBeenCalledWith(`users.${ada.id}.monitors`)

    const summaryCalls = vi.mocked(dashboardApi.getDashboardSummary).mock.calls.length
    const listCalls = vi.mocked(monitorsApi.listMonitors).mock.calls.length
    const cachedMonitor = makeMonitor({ notification_channel_ids: [7] })
    queryClient.setQueryData(queryKeys.monitor(cachedMonitor.id), cachedMonitor)
    const otherMonitorStatsKey = queryKeys.stats(cachedMonitor.id + 1, '24h')
    queryClient.setQueryData(otherMonitorStatsKey, { uptime_percentage: 100 })
    const { notification_channel_ids: _channelIds, ...checkedMonitor } = makeMonitor({ status: 'down', last_status_code: 503 })

    echo.listeners.get('.monitor.checked')?.({ monitor: checkedMonitor })

    expect(queryClient.getQueryData(queryKeys.monitor(cachedMonitor.id))).toEqual({ ...checkedMonitor, notification_channel_ids: [7] })
    await waitFor(() => expect(vi.mocked(monitorsApi.listMonitors).mock.calls.length).toBeGreaterThan(listCalls), { timeout: 3000 })
    await waitFor(() => expect(vi.mocked(dashboardApi.getDashboardSummary).mock.calls.length).toBeGreaterThan(summaryCalls), { timeout: 3000 })
    expect(queryClient.getQueryState(otherMonitorStatsKey)?.isInvalidated).toBe(false)
  })

  it('applies a check without a status change to the cached lists without refetching them', async () => {
    const echo = fakeEcho()
    vi.mocked(configApi.getAppConfig).mockResolvedValue(makeConfig({ realtime: { key: 'public-key' } }))

    const { queryClient } = renderApp('/dashboard')

    expect(await screen.findAllByRole('link', { name: 'Shop API' })).not.toHaveLength(0)
    await waitFor(() => expect(echo.listeners.has('.monitor.checked')).toBe(true))
    const monitor = makeMonitor()
    queryClient.setQueryData(queryKeys.monitorList(1), paginated([monitor]))
    queryClient.setQueryData(queryKeys.allMonitors, [monitor])
    const summaryCalls = vi.mocked(dashboardApi.getDashboardSummary).mock.calls.length
    const listCalls = vi.mocked(monitorsApi.listMonitors).mock.calls.length
    const { notification_channel_ids: _channelIds, ...checkedMonitor } = makeMonitor({ last_latency_ms: 999 })

    echo.listeners.get('.monitor.checked')?.({ monitor: checkedMonitor })

    expect(queryClient.getQueryData<PaginatedResponse<Monitor>>(queryKeys.monitorList(1))?.data[0].last_latency_ms).toBe(999)
    expect(queryClient.getQueryData<Monitor[]>(queryKeys.allMonitors)?.[0].last_latency_ms).toBe(999)
    await new Promise((resolve) => setTimeout(resolve, 700))
    expect(vi.mocked(monitorsApi.listMonitors).mock.calls.length).toBe(listCalls)
    expect(vi.mocked(dashboardApi.getDashboardSummary).mock.calls.length).toBe(summaryCalls)
  })

  it('does not connect when realtime is disabled', async () => {
    fakeEcho()
    vi.mocked(configApi.getAppConfig).mockResolvedValue(makeConfig())

    renderApp('/dashboard')

    expect(await screen.findAllByRole('link', { name: 'Shop API' })).not.toHaveLength(0)
    expect(realtimeApi.createEcho).not.toHaveBeenCalled()
  })

  it('refreshes incidents when an incident is acknowledged elsewhere', async () => {
    const echo = fakeEcho()
    vi.mocked(configApi.getAppConfig).mockResolvedValue(makeConfig({ realtime: { key: 'public-key' } }))
    renderApp('/dashboard')

    await waitFor(() => expect(echo.listeners.has('.incident.updated')).toBe(true))
    const summaryCalls = vi.mocked(dashboardApi.getDashboardSummary).mock.calls.length

    echo.listeners.get('.incident.updated')?.({ incident: { ...openIncident, acknowledged_at: '2026-10-17T10:05:00Z' } })

    await waitFor(() => expect(vi.mocked(dashboardApi.getDashboardSummary).mock.calls.length).toBeGreaterThan(summaryCalls), { timeout: 3000 })
  })
})
