import { screen } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as authApi from '../../api/auth.ts'
import * as dashboardApi from '../../api/dashboard.ts'
import * as monitorsApi from '../../api/monitors.ts'
import * as teamsApi from '../../api/teams.ts'
import * as usersApi from '../../api/users.ts'
import { ada, makeUser, paginated } from '../../test/fixtures.ts'
import { renderApp } from '../../test/renderApp.tsx'

vi.mock('../../api/auth.ts')
vi.mock('../../api/dashboard.ts')
vi.mock('../../api/monitors.ts')
vi.mock('../../api/teams.ts')
vi.mock('../../api/users.ts')

describe('SettingsPage', () => {
  beforeEach(() => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(ada)
    vi.mocked(usersApi.listUsers).mockResolvedValue([ada])
    vi.mocked(teamsApi.listTeams).mockResolvedValue([])
    vi.mocked(dashboardApi.getDashboardSummary).mockResolvedValue({ total: 0, up: 0, degraded: 0, down: 0, pending: 0, maintenance: 0, paused: 0 })
    vi.mocked(dashboardApi.listRecentIncidents).mockResolvedValue([])
    vi.mocked(monitorsApi.listMonitors).mockResolvedValue(paginated([]))
  })

  it('shows the instance settings and links to teams and users to administrators', async () => {
    renderApp('/settings')

    expect(await screen.findByRole('heading', { name: 'Instance' })).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Teams' })).not.toBeInTheDocument()
    expect(screen.getAllByRole('link', { name: 'Teams' }).length).toBeGreaterThan(0)
    expect(screen.getAllByRole('link', { name: 'Users' }).length).toBeGreaterThan(0)
  })

  it.each(['member', 'manager'] as const)('sends %ss back to the dashboard', async (role) => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(makeUser({ role }))
    const { router } = renderApp('/settings')

    expect(await screen.findByRole('heading', { name: 'Dashboard' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/dashboard')
    expect(screen.queryByRole('link', { name: 'Settings' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Teams' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Users' })).not.toBeInTheDocument()
    expect(usersApi.listUsers).not.toHaveBeenCalled()
  })

})
