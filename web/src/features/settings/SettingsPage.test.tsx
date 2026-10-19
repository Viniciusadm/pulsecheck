import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as authApi from '../../api/auth.ts'
import * as dashboardApi from '../../api/dashboard.ts'
import * as monitorsApi from '../../api/monitors.ts'
import * as teamsApi from '../../api/teams.ts'
import * as usersApi from '../../api/users.ts'
import { axiosError } from '../../test/axiosErrors.ts'
import { ada, makeTeam, makeUser, paginated } from '../../test/fixtures.ts'
import { renderApp } from '../../test/renderApp.tsx'

vi.mock('../../api/auth.ts')
vi.mock('../../api/dashboard.ts')
vi.mock('../../api/monitors.ts')
vi.mock('../../api/teams.ts')
vi.mock('../../api/users.ts')

async function teamsSection() {
  return within((await screen.findByRole('heading', { name: 'Teams' })).closest('section')!)
}

describe('SettingsPage', () => {
  beforeEach(() => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(ada)
    vi.mocked(usersApi.listUsers).mockResolvedValue([ada])
    vi.mocked(teamsApi.listTeams).mockResolvedValue([])
    vi.mocked(dashboardApi.getDashboardSummary).mockResolvedValue({ total: 0, up: 0, degraded: 0, down: 0, pending: 0, maintenance: 0, paused: 0 })
    vi.mocked(dashboardApi.listRecentIncidents).mockResolvedValue([])
    vi.mocked(monitorsApi.listMonitors).mockResolvedValue(paginated([]))
  })

  it('shows the instance, teams and users sections to administrators', async () => {
    renderApp('/settings')

    expect(await screen.findByRole('heading', { name: 'Instance' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Teams' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Users' })).toBeInTheDocument()
  })

  it.each(['member', 'manager'] as const)('sends %ss back to the dashboard', async (role) => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(makeUser({ role }))
    const { router } = renderApp('/settings')

    expect(await screen.findByRole('heading', { name: 'Dashboard' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/dashboard')
    expect(screen.queryByRole('link', { name: 'Settings' })).not.toBeInTheDocument()
    expect(usersApi.listUsers).not.toHaveBeenCalled()
  })

  it('lists teams with how many people and monitors they have', async () => {
    vi.mocked(teamsApi.listTeams).mockResolvedValue([makeTeam({ id: 1, name: 'Platform', users_count: 2, monitors_count: 3 })])
    renderApp('/settings')
    const section = await teamsSection()

    const row = (await section.findByText('Platform')).closest('li')!
    expect(within(row).getByText('2 people · 3 monitors')).toBeInTheDocument()
  })

  it('creates a team', async () => {
    vi.mocked(teamsApi.createTeam).mockResolvedValue(makeTeam({ id: 4, name: 'Commerce' }))
    const user = userEvent.setup()
    renderApp('/settings')
    const section = await teamsSection()

    await user.click(await section.findByRole('button', { name: 'Add team' }))
    const dialog = await screen.findByRole('dialog', { name: 'Add team' })
    await user.type(within(dialog).getByLabelText('Name'), '  Commerce ')
    await user.click(within(dialog).getByRole('button', { name: 'Add team' }))

    expect(await screen.findByText('Team added.')).toBeInTheDocument()
    expect(teamsApi.createTeam).toHaveBeenCalledWith({ name: 'Commerce' })
  })

  it('shows a duplicate name error next to the field', async () => {
    vi.mocked(teamsApi.createTeam).mockRejectedValue(axiosError(422, { message: 'Invalid.', errors: { name: ['The name has already been taken.'] } }))
    const user = userEvent.setup()
    renderApp('/settings')
    const section = await teamsSection()

    await user.click(await section.findByRole('button', { name: 'Add team' }))
    const dialog = await screen.findByRole('dialog', { name: 'Add team' })
    await user.type(within(dialog).getByLabelText('Name'), 'Platform')
    await user.click(within(dialog).getByRole('button', { name: 'Add team' }))

    expect(await within(dialog).findByText('The name has already been taken.')).toBeInTheDocument()
  })

  it('deletes a team after confirmation', async () => {
    vi.mocked(teamsApi.listTeams).mockResolvedValue([makeTeam({ id: 1, name: 'Platform' })])
    vi.mocked(teamsApi.deleteTeam).mockResolvedValue()
    const user = userEvent.setup()
    renderApp('/settings')
    const section = await teamsSection()

    await user.click(await section.findByRole('button', { name: 'Delete: Platform' }))
    const dialog = await screen.findByRole('dialog', { name: 'Delete Platform?' })
    await user.click(within(dialog).getByRole('button', { name: 'Delete team' }))

    expect(await screen.findByText('Team deleted.')).toBeInTheDocument()
    expect(teamsApi.deleteTeam).toHaveBeenCalledWith(1)
  })
})
