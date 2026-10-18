import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as authApi from '../../api/auth.ts'
import * as dashboardApi from '../../api/dashboard.ts'
import * as monitorsApi from '../../api/monitors.ts'
import { axiosError } from '../../test/axiosErrors.ts'
import { ada, makeMonitor, openIncident, paginated } from '../../test/fixtures.ts'
import { renderApp } from '../../test/renderApp.tsx'

vi.mock('../../api/auth.ts')
vi.mock('../../api/dashboard.ts')
vi.mock('../../api/monitors.ts')

describe('DashboardPage', () => {
  beforeEach(() => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(ada)
    vi.mocked(dashboardApi.listRecentIncidents).mockResolvedValue([])
  })

  it('shows placeholders while loading', async () => {
    vi.mocked(dashboardApi.getDashboardSummary).mockReturnValue(new Promise(() => {}))
    vi.mocked(monitorsApi.listMonitors).mockReturnValue(new Promise(() => {}))

    renderApp('/dashboard')

    expect(await screen.findByRole('heading', { name: 'Dashboard' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Dashboard' }).closest('[aria-busy="true"]')).not.toBeNull()
  })

  it('invites the user to add a first monitor when there are none', async () => {
    vi.mocked(dashboardApi.getDashboardSummary).mockResolvedValue({ total: 0, up: 0, down: 0, pending: 0, paused: 0 })
    vi.mocked(monitorsApi.listMonitors).mockResolvedValue(paginated([]))
    const user = userEvent.setup()

    renderApp('/dashboard')

    expect(await screen.findByText('Nothing is being monitored yet')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Add your first monitor' }))
    expect(await screen.findByRole('dialog', { name: 'Add monitor' })).toBeInTheDocument()
  })

  it('shows an error with a working retry', async () => {
    vi.mocked(dashboardApi.getDashboardSummary).mockRejectedValueOnce(axiosError(500)).mockResolvedValue({
      total: 1, up: 1, down: 0, pending: 0, paused: 0,
    })
    vi.mocked(monitorsApi.listMonitors).mockResolvedValue(paginated([makeMonitor()]))
    const user = userEvent.setup()

    renderApp('/dashboard')

    expect(await screen.findByRole('alert')).toHaveTextContent('Something went wrong on our side')
    await user.click(screen.getByRole('button', { name: 'Try again' }))
    expect(await screen.findByRole('heading', { name: 'Needs attention' })).toBeInTheDocument()
  })

  it('shows the summary as links to the filtered monitor list', async () => {
    vi.mocked(dashboardApi.getDashboardSummary).mockResolvedValue({ total: 3, up: 1, down: 1, pending: 0, paused: 1 })
    vi.mocked(monitorsApi.listMonitors).mockResolvedValue(paginated([]))

    renderApp('/dashboard')

    await screen.findByRole('link', { name: 'Up' })
    const summary = screen.getByRole('link', { name: 'Up' }).closest('dl')!
    const total = within(summary).getByRole('link', { name: 'Monitors' })
    expect(total).toHaveAttribute('href', '/monitors')
    expect(within(total.closest('div')!).getByText('3')).toBeInTheDocument()
    expect(within(summary).getByRole('link', { name: 'Up' })).toHaveAttribute('href', '/monitors?status=up')
    expect(within(summary).getByRole('link', { name: 'Down' })).toHaveAttribute('href', '/monitors?status=down')
    expect(within(summary).getByRole('link', { name: 'Paused' })).toHaveAttribute('href', '/monitors?status=paused')
  })

  it('lists only the monitors that are down under needs attention', async () => {
    vi.mocked(dashboardApi.getDashboardSummary).mockResolvedValue({ total: 3, up: 1, down: 1, pending: 0, paused: 1 })
    vi.mocked(monitorsApi.listMonitors).mockResolvedValue(
      paginated([makeMonitor({ id: 8, name: 'Blog', url: 'https://blog.example.com', status: 'down', last_status_code: 503 })]),
    )

    renderApp('/dashboard')

    const attention = await screen.findByRole('region', { name: 'Needs attention' })
    expect(monitorsApi.listMonitors).toHaveBeenCalledWith({ status: 'down' })
    expect(within(attention).getByRole('link', { name: 'Blog' })).toHaveAttribute('href', '/monitors/8')
    expect(within(attention).getByText('blog.example.com')).toBeInTheDocument()
    expect(within(attention).getByText('HTTP 503')).toBeInTheDocument()
    expect(within(attention).getByText('2 minutes ago')).toBeInTheDocument()
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
  })

  it('reassures the user when every monitor is healthy', async () => {
    vi.mocked(dashboardApi.getDashboardSummary).mockResolvedValue({ total: 1, up: 1, down: 0, pending: 0, paused: 0 })
    vi.mocked(monitorsApi.listMonitors).mockResolvedValue(paginated([]))

    renderApp('/dashboard')

    const attention = await screen.findByRole('region', { name: 'Needs attention' })
    expect(within(attention).getByText('All monitors are operating normally')).toBeInTheDocument()
  })

  it('shows recent incidents', async () => {
    vi.mocked(dashboardApi.getDashboardSummary).mockResolvedValue({ total: 1, up: 0, down: 1, pending: 0, paused: 0 })
    vi.mocked(monitorsApi.listMonitors).mockResolvedValue(paginated([]))
    vi.mocked(dashboardApi.listRecentIncidents).mockResolvedValue([openIncident])

    renderApp('/dashboard')

    const incidents = await screen.findByRole('region', { name: 'Recent incidents' })
    expect(within(incidents).getByText(openIncident.cause)).toBeInTheDocument()
    expect(within(incidents).getByText('Ongoing')).toBeInTheDocument()
    expect(within(incidents).getByText('10m')).toBeInTheDocument()
  })
})
