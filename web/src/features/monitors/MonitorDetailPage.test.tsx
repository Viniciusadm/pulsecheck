import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as authApi from '../../api/auth.ts'
import * as monitorsApi from '../../api/monitors.ts'
import { axiosError } from '../../test/axiosErrors.ts'
import { ada, emptyLatency, emptyUptimeDays, makeMonitor, makeStats, paginated } from '../../test/fixtures.ts'
import { renderApp } from '../../test/renderApp.tsx'

vi.mock('../../api/auth.ts')
vi.mock('../../api/monitors.ts')

describe('MonitorDetailPage', () => {
  beforeEach(() => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(ada)
    vi.mocked(monitorsApi.getMonitor).mockResolvedValue(makeMonitor())
    vi.mocked(monitorsApi.getMonitorStats).mockResolvedValue(makeStats())
    vi.mocked(monitorsApi.getLatencySeries).mockResolvedValue(emptyLatency)
    vi.mocked(monitorsApi.getUptimeHistory).mockResolvedValue(emptyUptimeDays)
    vi.mocked(monitorsApi.listMonitorIncidents).mockResolvedValue(paginated([]))
    vi.mocked(monitorsApi.listChecks).mockResolvedValue({ data: [], meta: { per_page: 50, next_cursor: null, prev_cursor: null } })
    vi.mocked(monitorsApi.listMonitors).mockResolvedValue(paginated([]))
  })

  it('shows "No data" instead of a percentage when there are no checks', async () => {
    renderApp('/monitors/7')

    const uptime = await screen.findByText('Uptime', { selector: 'dt' })
    expect(within(uptime.parentElement!).getByText('No data')).toBeInTheDocument()
    expect(within(uptime.parentElement!).getByText('No checks in this period')).toBeInTheDocument()
    expect(within(uptime.parentElement!).queryByText(/%/)).not.toBeInTheDocument()
  })

  it('shows uptime with its check counts', async () => {
    vi.mocked(monitorsApi.getMonitorStats).mockResolvedValue(makeStats({ total_checks: 10, successful_checks: 8, uptime_percentage: 80 }))

    renderApp('/monitors/7')

    const uptime = await screen.findByText('Uptime', { selector: 'dt' })
    expect(await within(uptime.parentElement!).findByText('80.00%')).toBeInTheDocument()
    expect(within(uptime.parentElement!).getByText('8 of 10 checks succeeded')).toBeInTheDocument()
  })

  it('marks days without data differently from full uptime', async () => {
    vi.mocked(monitorsApi.getUptimeHistory).mockResolvedValue([
      { date: '2026-10-16', total_checks: 0, successful_checks: 0, uptime_percentage: null },
      { date: '2026-10-17', total_checks: 288, successful_checks: 288, uptime_percentage: 100 },
    ])

    renderApp('/monitors/7')

    expect(await screen.findByLabelText('Oct 16: no data')).toBeInTheDocument()
    expect(screen.getByLabelText('Oct 17: 100% (288 checks)')).toHaveClass('bg-status-up')
  })

  it('asks for confirmation before deleting', async () => {
    vi.mocked(monitorsApi.deleteMonitor).mockResolvedValue()
    const user = userEvent.setup()
    const { router } = renderApp('/monitors/7')

    await user.click(await screen.findByRole('button', { name: 'Delete' }))

    const dialog = await screen.findByRole('dialog', { name: 'Delete Shop API?' })
    expect(monitorsApi.deleteMonitor).not.toHaveBeenCalled()

    await user.click(within(dialog).getByRole('button', { name: 'Cancel' }))
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(monitorsApi.deleteMonitor).not.toHaveBeenCalled()

    await user.click(screen.getByRole('button', { name: 'Delete' }))
    await user.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Delete monitor' }))

    expect(monitorsApi.deleteMonitor).toHaveBeenCalledWith(7)
    expect(await screen.findByText('Monitor deleted.')).toBeInTheDocument()
    await waitFor(() => expect(router.state.location.pathname).toBe('/monitors'))
  })

  it('shows why an invalid history period was rejected', async () => {
    vi.mocked(monitorsApi.listChecks).mockRejectedValue(
      axiosError(422, { message: 'Invalid.', errors: { from: ['The period cannot be longer than 90 days.'] } }),
    )

    renderApp('/monitors/7')

    expect(await screen.findByText('The period cannot be longer than 90 days.')).toBeInTheDocument()
  })

  it('closes the confirmation with the Escape key', async () => {
    const user = userEvent.setup()
    renderApp('/monitors/7')

    await user.click(await screen.findByRole('button', { name: 'Delete' }))
    expect(await screen.findByRole('dialog')).toHaveFocus()
    await user.keyboard('{Escape}')

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(monitorsApi.deleteMonitor).not.toHaveBeenCalled()
  })
})
