import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as authApi from '../../api/auth.ts'
import * as configApi from '../../api/config.ts'
import * as monitorsApi from '../../api/monitors.ts'
import * as statusPagesApi from '../../api/statusPages.ts'
import * as teamsApi from '../../api/teams.ts'
import type { PublicStatusPage, StatusPage } from '../../api/types.ts'
import { axiosError } from '../../test/axiosErrors.ts'
import { ada, makeConfig, makeMonitor, paginated } from '../../test/fixtures.ts'
import { renderApp } from '../../test/renderApp.tsx'

vi.mock('../../api/auth.ts')
vi.mock('../../api/config.ts')
vi.mock('../../api/monitors.ts')
vi.mock('../../api/statusPages.ts')
vi.mock('../../api/teams.ts')

function makeStatusPage(overrides: Partial<StatusPage> = {}): StatusPage {
  return {
    id: 1,
    name: 'Acme status',
    slug: 'acme',
    description: null,
    is_published: true,
    show_latency: false,
    show_incidents: true,
    uptime_days: 90,
    public_path: '/status/acme',
    monitors: [{ monitor_id: 7, name: 'Shop API', display_name: null, status: 'up' }],
    hidden_monitors_count: 0,
    team_ids: [],
    created_at: '2026-10-17T12:00:00Z',
    updated_at: '2026-10-17T12:00:00Z',
    ...overrides,
  }
}

const publicPage: PublicStatusPage = {
  name: 'Acme status',
  description: 'Live status of Acme services',
  generated_at: new Date().toISOString(),
  uptime_days: 30,
  overall_status: 'partial_outage',
  monitors: [
    { key: 1, name: 'Website', status: 'up', uptime_percentage: 100, latency_ms: 210, days: [{ date: '2026-10-17', uptime_percentage: 100 }] },
    { key: 2, name: 'API', status: 'down', uptime_percentage: 98.5, latency_ms: null, days: [{ date: '2026-10-17', uptime_percentage: 98.5 }] },
  ],
  incidents: [{ monitor_key: 2, started_at: '2026-10-17T10:00:00Z', resolved_at: null, duration_seconds: 600 }],
}

describe('status pages', () => {
  beforeEach(() => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(ada)
    vi.mocked(configApi.getAppConfig).mockResolvedValue(makeConfig())
    vi.mocked(teamsApi.listTeams).mockResolvedValue([])
    vi.mocked(monitorsApi.listMonitors).mockResolvedValue(paginated([makeMonitor({ id: 7, name: 'Shop API' }), makeMonitor({ id: 8, name: 'Blog' })]))
  })

  it('creates a page with ordered monitors and a public name', async () => {
    vi.mocked(statusPagesApi.listStatusPages).mockResolvedValue([])
    vi.mocked(statusPagesApi.createStatusPage).mockResolvedValue(makeStatusPage())
    const user = userEvent.setup()
    renderApp('/status-pages')

    await user.click(await screen.findByRole('button', { name: 'New status page' }))
    const dialog = await screen.findByRole('dialog', { name: 'New status page' })
    await user.type(within(dialog).getByLabelText('Name'), 'Acme Status Página')
    expect(within(dialog).getByLabelText('Address')).toHaveValue('acme-status-pagina')
    await user.click(within(dialog).getByLabelText('Published'))
    await user.selectOptions(await within(dialog).findByRole('combobox', { name: 'Add monitor' }), '7')
    await user.click(within(dialog).getByRole('button', { name: 'Add monitor' }))
    await user.selectOptions(within(dialog).getByRole('combobox', { name: 'Add monitor' }), '8')
    await user.click(within(dialog).getByRole('button', { name: 'Add monitor' }))
    await user.click(within(dialog).getByRole('button', { name: 'Move Blog up' }))
    await user.clear(within(dialog).getByLabelText('Public name of Shop API'))
    await user.type(within(dialog).getByLabelText('Public name of Shop API'), 'Shop')
    await user.click(within(dialog).getByRole('button', { name: 'Create page' }))

    expect(await screen.findByText('Status page created.')).toBeInTheDocument()
    expect(statusPagesApi.createStatusPage).toHaveBeenCalledWith(expect.objectContaining({
      slug: 'acme-status-pagina',
      is_published: true,
      monitors: [
        { monitor_id: 8, display_name: null },
        { monitor_id: 7, display_name: 'Shop' },
      ],
    }))
  })

  it('lists pages with their public link', async () => {
    vi.mocked(statusPagesApi.listStatusPages).mockResolvedValue([makeStatusPage()])
    renderApp('/status-pages')

    expect(await screen.findByText('Acme status')).toBeInTheDocument()
    expect(screen.getByText('Published')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Open page' })).toHaveAttribute('href', '/status/acme')
  })

  it('renders the public page without logging in', async () => {
    vi.mocked(authApi.fetchCurrentUser).mockRejectedValue(axiosError(401, { message: 'Unauthenticated.' }))
    vi.mocked(statusPagesApi.getPublicStatusPage).mockResolvedValue(publicPage)
    renderApp('/status/acme')

    expect(await screen.findByRole('heading', { name: 'Acme status' })).toBeInTheDocument()
    expect(screen.getByRole('status')).toHaveTextContent('Partial outage')
    expect(screen.getByText('Website')).toBeInTheDocument()
    expect(screen.getByText('210 ms average')).toBeInTheDocument()
    expect(screen.getAllByText('Outage')).not.toHaveLength(0)
    expect(screen.getByText('Ongoing')).toBeInTheDocument()
    expect(statusPagesApi.getPublicStatusPage).toHaveBeenCalledWith('acme')
  })

  it('explains when a public page does not exist', async () => {
    vi.mocked(authApi.fetchCurrentUser).mockRejectedValue(axiosError(401, { message: 'Unauthenticated.' }))
    vi.mocked(statusPagesApi.getPublicStatusPage).mockRejectedValue(axiosError(404, { message: 'Not found.' }))
    renderApp('/status/missing')

    expect(await screen.findByText('This status page does not exist or is not published.')).toBeInTheDocument()
  })
})
