import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as authApi from '../../api/auth.ts'
import * as configApi from '../../api/config.ts'
import * as maintenanceApi from '../../api/maintenanceWindows.ts'
import * as monitorsApi from '../../api/monitors.ts'
import * as teamsApi from '../../api/teams.ts'
import type { MaintenanceWindow } from '../../api/types.ts'
import { ada, makeConfig, makeMonitor, makeUser, paginated } from '../../test/fixtures.ts'
import { renderApp } from '../../test/renderApp.tsx'

vi.mock('../../api/auth.ts')
vi.mock('../../api/config.ts')
vi.mock('../../api/maintenanceWindows.ts')
vi.mock('../../api/monitors.ts')
vi.mock('../../api/teams.ts')

function makeWindow(overrides: Partial<MaintenanceWindow> = {}): MaintenanceWindow {
  return {
    id: 4,
    name: 'Nightly backup',
    type: 'recurring',
    starts_at: null,
    ends_at: null,
    weekdays: [1, 3],
    start_time: '23:00',
    end_time: '01:00',
    timezone: 'UTC',
    monitors: [{ id: 7, name: 'Shop API' }],
    monitor_ids: [7],
    team_ids: [],
    can_manage: true,
    active_until: null,
    next_starts_at: '2026-10-19T23:00:00Z',
    created_at: '2026-10-17T12:00:00Z',
    updated_at: '2026-10-17T12:00:00Z',
    ...overrides,
  }
}

describe('MaintenancePage', () => {
  beforeEach(() => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(ada)
    vi.mocked(configApi.getAppConfig).mockResolvedValue(makeConfig({ instance: { name: 'Acme', timezone: 'America/Sao_Paulo', locale: 'en' } }))
    vi.mocked(monitorsApi.listMonitors).mockResolvedValue(paginated([makeMonitor()]))
    vi.mocked(teamsApi.listTeams).mockResolvedValue([])
  })

  it('lists windows with their schedule and next start', async () => {
    vi.mocked(maintenanceApi.listMaintenanceWindows).mockResolvedValue(paginated([makeWindow()]))
    renderApp('/maintenance')

    expect(await screen.findByText('Nightly backup')).toBeInTheDocument()
    expect(screen.getByText('Mon, Wed, 23:00 to 01:00 (UTC)')).toBeInTheDocument()
    expect(screen.getByText(/^Next /)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Shop API' })).toHaveAttribute('href', '/monitors/7')
  })

  it('schedules a one-off window in the instance time zone', async () => {
    vi.mocked(maintenanceApi.listMaintenanceWindows).mockResolvedValue(paginated([]))
    vi.mocked(maintenanceApi.createMaintenanceWindow).mockResolvedValue(makeWindow())
    const user = userEvent.setup()
    renderApp('/maintenance')

    await user.click(await screen.findByRole('button', { name: 'Schedule maintenance' }))
    const dialog = await screen.findByRole('dialog', { name: 'Schedule maintenance' })
    await user.type(within(dialog).getByLabelText('Name'), 'Database upgrade')
    await user.type(within(dialog).getByLabelText('Starts'), '2026-10-17T22:00')
    await user.type(within(dialog).getByLabelText('Ends'), '2026-10-17T23:30')
    await user.click(await within(dialog).findByRole('checkbox', { name: /Shop API/ }))
    await user.click(within(dialog).getByRole('button', { name: 'Schedule' }))

    expect(await screen.findByText('Maintenance window scheduled.')).toBeInTheDocument()
    expect(maintenanceApi.createMaintenanceWindow).toHaveBeenCalledWith({
      name: 'Database upgrade',
      type: 'one_off',
      starts_at: '2026-10-18T01:00:00.000Z',
      ends_at: '2026-10-18T02:30:00.000Z',
      monitor_ids: [7],
      team_ids: [],
    })
  })

  it('hides management actions on windows shared with targets outside the user scope', async () => {
    vi.mocked(maintenanceApi.listMaintenanceWindows).mockResolvedValue(paginated([makeWindow(), makeWindow({ id: 5, name: 'Shared window', can_manage: false })]))
    renderApp('/maintenance')

    expect(await screen.findByText('Shared window')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Edit Nightly backup' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Shared window' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Delete Shared window' })).not.toBeInTheDocument()
  })

  it('pages through the windows', async () => {
    vi.mocked(maintenanceApi.listMaintenanceWindows).mockImplementation(async ({ page = 1 } = {}) => ({
      data: [makeWindow({ id: page, name: `Window ${page}` })],
      meta: { current_page: page, last_page: 2, per_page: 20, total: 21 },
    }))
    renderApp('/maintenance')

    expect(await screen.findByText('Window 1')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Next' }))

    expect(await screen.findByText('Window 2')).toBeInTheDocument()
    expect(maintenanceApi.listMaintenanceWindows).toHaveBeenLastCalledWith({ page: 2 })
  })

  it('hides management actions from members', async () => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(makeUser())
    vi.mocked(maintenanceApi.listMaintenanceWindows).mockResolvedValue(paginated([makeWindow()]))
    renderApp('/maintenance')

    expect(await screen.findByText('Nightly backup')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Schedule maintenance' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Nightly backup' })).not.toBeInTheDocument()
  })
})
