import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as authApi from '../../api/auth.ts'
import * as dashboardApi from '../../api/dashboard.ts'
import * as monitorsApi from '../../api/monitors.ts'
import * as teamsApi from '../../api/teams.ts'
import * as usersApi from '../../api/users.ts'
import { axiosError } from '../../test/axiosErrors.ts'
import { ada, makeMonitor, makeTeam, makeUser, paginated } from '../../test/fixtures.ts'
import { renderApp } from '../../test/renderApp.tsx'

vi.mock('../../api/auth.ts')
vi.mock('../../api/dashboard.ts')
vi.mock('../../api/monitors.ts')
vi.mock('../../api/teams.ts')
vi.mock('../../api/users.ts')

const grace = makeUser({ id: 2, name: 'Grace Hopper', email: 'grace@example.com' })
const alan = makeUser({ id: 3, name: 'Alan Turing', email: 'alan@example.com' })
const shopApi = makeMonitor({ id: 7, name: 'Shop API', team_ids: [1] })
const blog = makeMonitor({ id: 8, name: 'Blog', team_ids: [] })

describe('Teams pages', () => {
  beforeEach(() => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(ada)
    vi.mocked(usersApi.listUsers).mockResolvedValue([ada, grace, alan])
    vi.mocked(teamsApi.listTeams).mockResolvedValue([])
    vi.mocked(monitorsApi.listMonitors).mockResolvedValue(paginated([shopApi, blog]))
    vi.mocked(dashboardApi.getDashboardSummary).mockResolvedValue({ total: 0, up: 0, degraded: 0, down: 0, pending: 0, maintenance: 0, paused: 0 })
    vi.mocked(dashboardApi.listRecentIncidents).mockResolvedValue([])
  })

  it('lists teams with how many people and monitors they have', async () => {
    vi.mocked(teamsApi.listTeams).mockResolvedValue([makeTeam({ id: 1, name: 'Platform', users_count: 2, monitors_count: 3 })])
    renderApp('/teams')

    const row = (await screen.findByRole('link', { name: 'Platform' })).closest('li')!
    expect(within(row).getByText('2 people · 3 monitors')).toBeInTheDocument()
  })

  it('sends members back to the dashboard', async () => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(makeUser({ role: 'manager' }))
    const { router } = renderApp('/teams/1')

    expect(await screen.findByRole('heading', { name: 'Dashboard' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/dashboard')
    expect(teamsApi.getTeam).not.toHaveBeenCalled()
  })

  it('creates a team with people and monitors and opens it', async () => {
    const created = makeTeam({ id: 4, name: 'Commerce', user_ids: [2], monitor_ids: [8] })
    vi.mocked(teamsApi.createTeam).mockResolvedValue(created)
    vi.mocked(teamsApi.getTeam).mockResolvedValue(created)
    const user = userEvent.setup()
    const { router } = renderApp('/teams/new')

    await user.type(await screen.findByLabelText('Name'), '  Commerce ')
    const personSelect = await screen.findByLabelText('Add person')
    expect(within(personSelect).queryByRole('option', { name: /Ada Lovelace/ })).not.toBeInTheDocument()
    await user.selectOptions(personSelect, 'Grace Hopper (grace@example.com)')
    await user.click(screen.getAllByRole('button', { name: 'Add' })[0])
    await user.selectOptions(await screen.findByLabelText('Add monitor'), 'Blog')
    await user.click(screen.getAllByRole('button', { name: 'Add' })[1])
    await user.click(screen.getByRole('button', { name: 'Add team' }))

    expect(await screen.findByText('Team added.')).toBeInTheDocument()
    expect(teamsApi.createTeam).toHaveBeenCalledWith({ name: 'Commerce', user_ids: [2], monitor_ids: [8] })
    expect(router.state.location.pathname).toBe('/teams/4')
  })

  it('removes a person and a monitor from a team and warns about monitors left without a team', async () => {
    const team = makeTeam({ id: 1, name: 'Platform', user_ids: [2, 3], monitor_ids: [7] })
    vi.mocked(teamsApi.getTeam).mockResolvedValue(team)
    vi.mocked(teamsApi.updateTeam).mockResolvedValue({ ...team, user_ids: [3], monitor_ids: [] })
    const user = userEvent.setup()
    const { router } = renderApp('/teams/1')

    expect(await screen.findByRole('heading', { name: 'Edit Platform' })).toBeInTheDocument()
    await user.click(await screen.findByRole('button', { name: 'Remove Grace Hopper' }))
    await user.click(await screen.findByRole('button', { name: 'Remove Shop API' }))

    expect(screen.getByText(/will not belong to any team.*Shop API/)).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Save changes' }))

    expect(await screen.findByText('Changes saved.')).toBeInTheDocument()
    expect(teamsApi.updateTeam).toHaveBeenCalledWith(1, { name: 'Platform', user_ids: [3], monitor_ids: [] })
    expect(router.state.location.pathname).toBe('/teams')
  })

  it('shows a duplicate name error next to the field', async () => {
    vi.mocked(teamsApi.createTeam).mockRejectedValue(axiosError(422, { message: 'Invalid.', errors: { name: ['The name has already been taken.'] } }))
    const user = userEvent.setup()
    renderApp('/teams/new')

    await user.type(await screen.findByLabelText('Name'), 'Platform')
    await user.click(screen.getByRole('button', { name: 'Add team' }))

    expect(await screen.findByText('The name has already been taken.')).toBeInTheDocument()
  })

  it('shows not found for an unknown team', async () => {
    vi.mocked(teamsApi.getTeam).mockRejectedValue(axiosError(404, { message: 'Not found.' }))
    renderApp('/teams/99')

    expect(await screen.findByText('Team not found')).toBeInTheDocument()
  })

  it('deletes a team after confirmation', async () => {
    vi.mocked(teamsApi.listTeams).mockResolvedValue([makeTeam({ id: 1, name: 'Platform' })])
    vi.mocked(teamsApi.deleteTeam).mockResolvedValue()
    const user = userEvent.setup()
    renderApp('/teams')

    await user.click(await screen.findByRole('button', { name: 'Delete: Platform' }))
    const dialog = await screen.findByRole('dialog', { name: 'Delete Platform?' })
    await user.click(within(dialog).getByRole('button', { name: 'Delete team' }))

    expect(await screen.findByText('Team deleted.')).toBeInTheDocument()
    expect(teamsApi.deleteTeam).toHaveBeenCalledWith(1)
  })
})
