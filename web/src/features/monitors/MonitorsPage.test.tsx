import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as authApi from '../../api/auth.ts'
import * as configApi from '../../api/config.ts'
import * as monitorsApi from '../../api/monitors.ts'
import * as channelsApi from '../../api/notificationChannels.ts'
import * as teamsApi from '../../api/teams.ts'
import { ada, makeMonitor, makeUser, paginated } from '../../test/fixtures.ts'
import { renderApp } from '../../test/renderApp.tsx'

vi.mock('../../api/auth.ts')
vi.mock('../../api/config.ts')
vi.mock('../../api/monitors.ts')
vi.mock('../../api/notificationChannels.ts')
vi.mock('../../api/teams.ts')

describe('MonitorsPage', () => {
  beforeEach(() => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(ada)
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

  it('shows monitors read-only to members', async () => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(makeUser())
    vi.mocked(monitorsApi.listMonitors).mockResolvedValue(paginated([makeMonitor()]))
    renderApp('/monitors')

    expect(await screen.findAllByRole('link', { name: 'Shop API' })).not.toHaveLength(0)
    expect(screen.queryByRole('button', { name: 'Add monitor' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Shop API' })).not.toBeInTheDocument()
  })

  it('edits a monitor straight from the list', async () => {
    vi.mocked(monitorsApi.listMonitors).mockResolvedValue(paginated([makeMonitor()]))
    vi.mocked(monitorsApi.updateMonitor).mockResolvedValue(makeMonitor({ name: 'Shop API v2' }))
    const user = userEvent.setup()
    const { router } = renderApp('/monitors')

    const [editButton] = await screen.findAllByRole('button', { name: 'Edit Shop API' })
    await user.click(editButton)

    const dialog = await screen.findByRole('dialog', { name: 'Edit monitor' })
    const nameField = within(dialog).getByLabelText('Name')
    expect(nameField).toHaveValue('Shop API')

    await user.clear(nameField)
    await user.type(nameField, 'Shop API v2')
    await user.click(within(dialog).getByRole('button', { name: 'Save changes' }))

    await waitFor(() => expect(monitorsApi.updateMonitor).toHaveBeenCalledWith(7, expect.objectContaining({ name: 'Shop API v2' })))
    expect(await screen.findByText('Changes saved.')).toBeInTheDocument()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/monitors')
  })

  it('offers to go back when the page is past the last one', async () => {
    vi.mocked(monitorsApi.listMonitors).mockImplementation(async ({ page } = {}) => ({
      data: page === 1 ? [makeMonitor()] : [],
      meta: { current_page: page ?? 1, last_page: 1, per_page: 20, total: 1 },
    }))
    const user = userEvent.setup()
    const { router } = renderApp('/monitors?page=3')

    expect(await screen.findByText('This page is empty.')).toBeInTheDocument()
    expect(screen.queryByText('Nothing is being monitored yet')).not.toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Go to the first page' }))

    expect(await screen.findByRole('link', { name: 'Shop API' })).toBeInTheDocument()
    await waitFor(() => expect(router.state.location.search).toBe(''))
  })
})
