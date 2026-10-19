import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as authApi from '../../api/auth.ts'
import * as dashboardApi from '../../api/dashboard.ts'
import * as monitorsApi from '../../api/monitors.ts'
import { axiosError } from '../../test/axiosErrors.ts'
import { mockDashboardApi } from '../../test/dashboardMocks.ts'
import { ada, emptyLatency, makeDashboardLayout, makeMonitor, openIncident, paginated } from '../../test/fixtures.ts'
import { renderApp } from '../../test/renderApp.tsx'

vi.mock('../../api/auth.ts')
vi.mock('../../api/dashboard.ts')
vi.mock('../../api/monitors.ts')

describe('DashboardPage', () => {
  beforeEach(() => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(ada)
    mockDashboardApi()
    vi.mocked(dashboardApi.getDashboardSummary).mockResolvedValue({ total: 3, up: 1, degraded: 0, down: 1, pending: 0, paused: 1 })
    vi.mocked(monitorsApi.listMonitors).mockResolvedValue(paginated([]))
  })

  it('shows placeholders while loading', async () => {
    vi.mocked(dashboardApi.getDashboardSummary).mockReturnValue(new Promise(() => {}))

    renderApp('/dashboard')

    expect(await screen.findByRole('heading', { name: 'Dashboard' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Dashboard' }).closest('[aria-busy="true"]')).not.toBeNull()
  })

  it('invites the user to add a first monitor when there are none', async () => {
    vi.mocked(dashboardApi.getDashboardSummary).mockResolvedValue({ total: 0, up: 0, degraded: 0, down: 0, pending: 0, paused: 0 })
    const user = userEvent.setup()

    renderApp('/dashboard')

    expect(await screen.findByText('Nothing is being monitored yet')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Add your first monitor' }))
    expect(await screen.findByRole('dialog', { name: 'Add monitor' })).toBeInTheDocument()
  })

  it('shows an error with a working retry', async () => {
    vi.mocked(dashboardApi.getDashboardLayout).mockRejectedValueOnce(axiosError(500))
    const user = userEvent.setup()

    renderApp('/dashboard')

    expect(await screen.findByRole('alert')).toHaveTextContent('Something went wrong on our side')
    await user.click(screen.getByRole('button', { name: 'Try again' }))
    expect(await screen.findByRole('heading', { name: 'Needs attention' })).toBeInTheDocument()
  })

  it('renders every widget of the saved layout', async () => {
    renderApp('/dashboard')

    for (const title of ['Status summary', 'Uptime', 'Status distribution', 'Response time', 'Slowest monitors', 'Needs attention', 'Recent incidents']) {
      expect(await screen.findByRole('region', { name: title })).toBeInTheDocument()
    }
  })

  it('shows the summary as links to the filtered monitor list', async () => {
    renderApp('/dashboard')

    const summary = await screen.findByRole('region', { name: 'Status summary' })
    const total = await within(summary).findByRole('link', { name: 'Monitors' })
    expect(total).toHaveAttribute('href', '/monitors')
    expect(within(total.closest('div')!).getByText('3')).toBeInTheDocument()
    expect(within(summary).getByRole('link', { name: 'Up' })).toHaveAttribute('href', '/monitors?status=up')
    expect(within(summary).getByRole('link', { name: 'Down' })).toHaveAttribute('href', '/monitors?status=down')
    expect(within(summary).getByRole('link', { name: 'Paused' })).toHaveAttribute('href', '/monitors?status=paused')
  })

  it('notes pending monitors inside the total card', async () => {
    vi.mocked(dashboardApi.getDashboardSummary).mockResolvedValue({ total: 3, up: 0, degraded: 0, down: 0, pending: 3, paused: 0 })

    renderApp('/dashboard')

    const summary = await screen.findByRole('region', { name: 'Status summary' })
    const total = await within(summary).findByRole('link', { name: 'Monitors' })
    expect(within(total.closest('div')!).getByText('3 awaiting first check')).toBeInTheDocument()
  })

  it('lists only the monitors that are down under needs attention', async () => {
    vi.mocked(monitorsApi.listMonitors).mockResolvedValue(
      paginated([makeMonitor({ id: 8, name: 'Blog', url: 'https://blog.example.com', display_target: 'blog.example.com', status: 'down', last_status_code: 503 })]),
    )

    renderApp('/dashboard')

    const attention = await screen.findByRole('region', { name: 'Needs attention' })
    expect(monitorsApi.listMonitors).toHaveBeenCalledWith({ status: 'down' })
    expect(await within(attention).findByRole('link', { name: 'Blog' })).toHaveAttribute('href', '/monitors/8')
    expect(within(attention).getByText('blog.example.com')).toBeInTheDocument()
    expect(within(attention).getByText('HTTP 503')).toBeInTheDocument()
    expect(within(attention).getByText('2 minutes ago')).toBeInTheDocument()
  })

  it('reassures the user when every monitor is healthy', async () => {
    renderApp('/dashboard')

    const attention = await screen.findByRole('region', { name: 'Needs attention' })
    expect(await within(attention).findByText('All monitors are operating normally')).toBeInTheDocument()
  })

  it('shows recent incidents', async () => {
    vi.mocked(dashboardApi.listRecentIncidents).mockResolvedValue([openIncident])

    renderApp('/dashboard')

    const incidents = await screen.findByRole('region', { name: 'Recent incidents' })
    expect(await within(incidents).findByText(openIncident.cause)).toBeInTheDocument()
    expect(within(incidents).getByText('Ongoing')).toBeInTheDocument()
  })

  it('charts aggregated metrics for the saved period', async () => {
    mockDashboardApi(makeDashboardLayout({ period: '7d' }))
    vi.mocked(dashboardApi.getUptimeTrend).mockResolvedValue([
      { bucket_start: '2026-10-18T10:00:00Z', checks: 3, failed_checks: 0, uptime_percentage: 100 },
      { bucket_start: '2026-10-18T11:00:00Z', checks: 1, failed_checks: 1, uptime_percentage: 0 },
    ])
    vi.mocked(dashboardApi.getSlowestMonitors).mockResolvedValue([{ monitor_id: 8, name: 'Blog', average_latency_ms: 1250 }])

    renderApp('/dashboard')

    const uptime = await screen.findByRole('region', { name: 'Uptime' })
    expect(await within(uptime).findByText('75.00%')).toBeInTheDocument()
    const slowest = screen.getByRole('region', { name: 'Slowest monitors' })
    expect(await within(slowest).findByRole('link', { name: 'Blog' })).toHaveAttribute('href', '/monitors/8')
    expect(within(slowest).getByText('1.25 s')).toBeInTheDocument()
    expect(dashboardApi.getUptimeTrend).toHaveBeenCalledWith('7d')
    expect(screen.getByRole('button', { name: '7d' })).toHaveAttribute('aria-pressed', 'true')
  })

  it('saves the period as soon as it changes and reloads the charts', async () => {
    vi.mocked(dashboardApi.updateDashboardLayout).mockResolvedValue(makeDashboardLayout({ period: '30d' }))
    const user = userEvent.setup()

    renderApp('/dashboard')

    await screen.findByRole('region', { name: 'Uptime' })
    await user.click(screen.getByRole('button', { name: '30d' }))

    expect(dashboardApi.updateDashboardLayout).toHaveBeenCalledWith({ period: '30d' })
    await waitFor(() => expect(dashboardApi.getUptimeTrend).toHaveBeenCalledWith('30d'))
    expect(screen.getByRole('button', { name: '30d' })).toHaveAttribute('aria-pressed', 'true')
  })

  it('lets the user remove and add charts, then saves the layout', async () => {
    vi.mocked(monitorsApi.listMonitors).mockResolvedValue(paginated([makeMonitor({ id: 7, name: 'Shop API' })]))
    vi.mocked(monitorsApi.getMonitor).mockResolvedValue(makeMonitor({ id: 7, name: 'Shop API' }))
    vi.mocked(monitorsApi.getLatencySeries).mockResolvedValue(emptyLatency)
    vi.mocked(dashboardApi.updateDashboardLayout).mockImplementation(async (input) => makeDashboardLayout({ ...input, is_default: false }))
    const user = userEvent.setup()

    renderApp('/dashboard')

    await user.click(await screen.findByRole('button', { name: 'Customize' }))
    await user.click(screen.getByRole('button', { name: 'Remove Slowest monitors' }))
    expect(screen.queryByRole('region', { name: 'Slowest monitors' })).not.toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Add chart' }))
    const dialog = await screen.findByRole('dialog', { name: 'Add a chart' })
    expect(within(dialog).getByRole('radio', { name: /Uptime/ })).toBeDisabled()
    await user.click(within(dialog).getByRole('radio', { name: /Monitor response time/ }))
    expect(within(dialog).getByRole('button', { name: 'Add' })).toBeDisabled()
    await user.selectOptions(await within(dialog).findByRole('combobox', { name: 'Monitor' }), await within(dialog).findByRole('option', { name: 'Shop API' }))
    await user.click(within(dialog).getByRole('button', { name: 'Add' }))

    expect(await screen.findByRole('region', { name: 'Monitor response time' })).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Save layout' }))

    await waitFor(() => expect(dashboardApi.updateDashboardLayout).toHaveBeenCalledTimes(1))
    const saved = vi.mocked(dashboardApi.updateDashboardLayout).mock.calls[0][0]
    expect(saved.period).toBe('24h')
    expect(saved.widgets?.map((widget) => widget.type)).toEqual([
      'status_summary',
      'uptime_trend',
      'status_distribution',
      'latency_trend',
      'down_monitors',
      'recent_incidents',
      'monitor_latency',
    ])
    expect(saved.widgets?.at(-1)).toMatchObject({ x: 0, y: 14, w: 6, h: 4, options: { monitor_id: 7 } })
    expect(await screen.findByText('Dashboard saved')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Customize' })).toBeInTheDocument()
  })

  it('discards unsaved changes on cancel', async () => {
    const user = userEvent.setup()

    renderApp('/dashboard')

    await user.click(await screen.findByRole('button', { name: 'Customize' }))
    await user.click(screen.getByRole('button', { name: 'Remove Uptime' }))
    await user.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(screen.getByRole('region', { name: 'Uptime' })).toBeInTheDocument()
    expect(dashboardApi.updateDashboardLayout).not.toHaveBeenCalled()
  })

  it('restores the default layout after confirmation', async () => {
    mockDashboardApi(makeDashboardLayout({ is_default: false, widgets: [] }))
    vi.mocked(dashboardApi.resetDashboardLayout).mockResolvedValue(makeDashboardLayout())
    const user = userEvent.setup()

    renderApp('/dashboard')

    expect(await screen.findByText('Your dashboard is empty')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Customize' }))
    await user.click(screen.getByRole('button', { name: 'Restore default' }))
    await user.click(within(await screen.findByRole('dialog', { name: 'Restore the default dashboard?' })).getByRole('button', { name: 'Restore default' }))

    expect(await screen.findByRole('region', { name: 'Status summary' })).toBeInTheDocument()
    expect(dashboardApi.resetDashboardLayout).toHaveBeenCalled()
  })
})
