import { screen, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as authApi from '../../api/auth.ts'
import * as configApi from '../../api/config.ts'
import * as dashboardApi from '../../api/dashboard.ts'
import * as monitorsApi from '../../api/monitors.ts'
import * as realtimeApi from '../../api/realtime.ts'
import type { Monitor } from '../../api/types.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import { ada, makeConfig, makeMonitor, paginated } from '../../test/fixtures.ts'
import { renderApp } from '../../test/renderApp.tsx'

vi.mock('../../api/auth.ts')
vi.mock('../../api/dashboard.ts')
vi.mock('../../api/monitors.ts')
vi.mock('../../api/realtime.ts')

type Listener = (event: { monitor: Monitor }) => void

function fakeEcho() {
  const listeners = new Map<string, Listener>()
  const privateChannel = vi.fn(() => ({
    listen: (event: string, listener: Listener) => {
      listeners.set(event, listener)
    },
  }))
  const disconnect = vi.fn()

  vi.mocked(realtimeApi.createEcho).mockReturnValue({ private: privateChannel, disconnect } as unknown as ReturnType<typeof realtimeApi.createEcho>)

  return { listeners, privateChannel, disconnect }
}

describe('useMonitorRealtime', () => {
  beforeEach(() => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(ada)
    vi.mocked(dashboardApi.getDashboardSummary).mockResolvedValue({ total: 1, up: 1, down: 0, pending: 0, paused: 0 })
    vi.mocked(dashboardApi.listRecentIncidents).mockResolvedValue([])
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
    const checkedMonitor = makeMonitor({ status: 'down', last_status_code: 503 })

    echo.listeners.get('.monitor.checked')?.({ monitor: checkedMonitor })

    expect(queryClient.getQueryData(queryKeys.monitor(checkedMonitor.id))).toEqual(checkedMonitor)
    await waitFor(() => expect(vi.mocked(monitorsApi.listMonitors).mock.calls.length).toBeGreaterThan(listCalls))
    expect(vi.mocked(dashboardApi.getDashboardSummary).mock.calls.length).toBeGreaterThan(summaryCalls)
  })

  it('does not connect when realtime is disabled', async () => {
    fakeEcho()
    vi.mocked(configApi.getAppConfig).mockResolvedValue(makeConfig())

    renderApp('/dashboard')

    expect(await screen.findAllByRole('link', { name: 'Shop API' })).not.toHaveLength(0)
    expect(realtimeApi.createEcho).not.toHaveBeenCalled()
  })
})
