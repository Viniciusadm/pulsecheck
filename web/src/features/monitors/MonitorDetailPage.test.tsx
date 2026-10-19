import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as authApi from '../../api/auth.ts'
import * as configApi from '../../api/config.ts'
import * as monitorsApi from '../../api/monitors.ts'
import * as channelsApi from '../../api/notificationChannels.ts'
import * as teamsApi from '../../api/teams.ts'
import { axiosError } from '../../test/axiosErrors.ts'
import { formatTime } from '../../lib/format.ts'
import { ada, emptyLatency, emptyUptimeDays, makeMonitor, makeStats, makeUser, paginated } from '../../test/fixtures.ts'
import { renderApp } from '../../test/renderApp.tsx'

vi.mock('../../api/auth.ts')
vi.mock('../../api/config.ts')
vi.mock('../../api/monitors.ts')
vi.mock('../../api/notificationChannels.ts')
vi.mock('../../api/teams.ts')

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
    vi.mocked(channelsApi.listNotificationChannels).mockResolvedValue([])
    vi.mocked(teamsApi.listTeams).mockResolvedValue([])
    vi.mocked(configApi.getAppConfig).mockResolvedValue({
      setup_required: false,
      setup_token_required: false,
      instance: { name: 'PulseCheck', timezone: 'UTC', locale: 'en' },
      available_locales: ['en'],
      monitors: { allowed_intervals: [1, 5, 10], heartbeat_intervals: [5, 60, 1440], keyword_max_body_bytes: 1048576, default_interval: 5, timeout_min: 1, timeout_max: 30, timeout_default: 10 },
      realtime: null,
    })
  })

  it('hides edit, pause and delete from members', async () => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(makeUser())
    renderApp('/monitors/7')

    expect(await screen.findByRole('heading', { name: 'Shop API' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Pause' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Delete' })).not.toBeInTheDocument()
  })

  it('shows when the next check is scheduled', async () => {
    const nextCheckAt = new Date(Date.now() + 180_000).toISOString()
    vi.mocked(monitorsApi.getMonitor).mockResolvedValue(makeMonitor({ next_check_at: nextCheckAt }))

    renderApp('/monitors/7')

    expect(await screen.findByText(`Next check at ${formatTime(nextCheckAt)}`)).toBeInTheDocument()
  })

  it('hides the next check while paused', async () => {
    vi.mocked(monitorsApi.getMonitor).mockResolvedValue(makeMonitor({ is_paused: true, next_check_at: new Date().toISOString() }))

    renderApp('/monitors/7')

    await screen.findByText('Shop API', { selector: 'h1' })
    expect(screen.queryByText(/Next check at/)).not.toBeInTheDocument()
  })

  it('shows "No data" instead of a percentage when there are no checks', async () => {
    renderApp('/monitors/7')

    const uptime = await screen.findByText('Uptime', { selector: 'dt' })
    expect(within(uptime.parentElement!).getByText('No data')).toBeInTheDocument()
    expect(within(uptime.parentElement!).getByText('No checks in this period')).toBeInTheDocument()
    expect(within(uptime.parentElement!).queryByText(/%/)).not.toBeInTheDocument()
  })

  it('shows uptime with its check counts', async () => {
    vi.mocked(monitorsApi.getMonitorStats).mockResolvedValue(makeStats({ total_checks: 10, successful_checks: 8, degraded_checks: 0, uptime_percentage: 80 }))

    renderApp('/monitors/7')

    const uptime = await screen.findByText('Uptime', { selector: 'dt' })
    expect(await within(uptime.parentElement!).findByText('80.00%')).toBeInTheDocument()
    expect(within(uptime.parentElement!).getByText('8 of 10 checks succeeded')).toBeInTheDocument()
  })

  it('marks days without data differently from full uptime', async () => {
    vi.mocked(monitorsApi.getUptimeHistory).mockResolvedValue([
      { date: '2026-10-16', total_checks: 0, successful_checks: 0, degraded_checks: 0, uptime_percentage: null },
      { date: '2026-10-17', total_checks: 288, successful_checks: 288, degraded_checks: 0, uptime_percentage: 100 },
    ])

    renderApp('/monitors/7')

    expect(await screen.findByLabelText('Oct 16: no data')).toBeInTheDocument()
    expect(screen.getByLabelText('Oct 17: 100% (288 checks)')).toHaveClass('bg-status-up')
  })

  it('edits the monitor from the detail page', async () => {
    vi.mocked(monitorsApi.updateMonitor).mockResolvedValue(makeMonitor({ interval_minutes: 10 }))
    const user = userEvent.setup()
    renderApp('/monitors/7')

    await user.click(await screen.findByRole('button', { name: 'Edit' }))

    const dialog = await screen.findByRole('dialog', { name: 'Edit monitor' })
    await user.selectOptions(within(dialog).getByLabelText('Check every'), '10')
    await user.click(within(dialog).getByRole('button', { name: 'Save changes' }))

    await waitFor(() => expect(monitorsApi.updateMonitor).toHaveBeenCalledWith(7, expect.objectContaining({ interval_minutes: 10 })))
    expect(await screen.findByText('Changes saved.')).toBeInTheDocument()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
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

  it('shows the ping URL of a heartbeat monitor and regenerates it', async () => {
    const heartbeat = makeMonitor({
      type: 'heartbeat',
      url: null,
      display_target: 'Heartbeat',
      heartbeat_url: 'https://pulse.example.com/api/heartbeat/abc',
    })
    vi.mocked(monitorsApi.getMonitor).mockResolvedValue(heartbeat)
    vi.mocked(monitorsApi.rotateHeartbeatToken).mockResolvedValue({ ...heartbeat, heartbeat_url: 'https://pulse.example.com/api/heartbeat/new' })
    const user = userEvent.setup()
    renderApp('/monitors/7')

    expect(await screen.findByText('https://pulse.example.com/api/heartbeat/abc')).toBeInTheDocument()
    expect(screen.getByText('No call received yet')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Generate new URL' }))
    await user.click(within(await screen.findByRole('dialog', { name: 'Generate a new URL?' })).getByRole('button', { name: 'Generate new URL' }))

    expect(await screen.findByText('New URL generated.')).toBeInTheDocument()
    expect(monitorsApi.rotateHeartbeatToken).toHaveBeenCalledWith(7)
  })
})
