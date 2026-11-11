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
      monitors: { allowed_intervals: [1, 5, 10], heartbeat_intervals: [5, 60, 1440], keyword_max_body_bytes: 1048576, reminder_intervals: [15, 30, 60], default_interval: 5, timeout_min: 1, timeout_max: 30, timeout_default: 10 },
      realtime: null,
      status_page_domain: null,
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
    vi.mocked(monitorsApi.getMonitor).mockResolvedValue(makeMonitor())
    const user = userEvent.setup()
    const { router } = renderApp('/monitors')

    const [editButton] = await screen.findAllByRole('button', { name: 'Edit Shop API' })
    await user.click(editButton)

    const form = await screen.findByRole('form', { name: 'Edit monitor' })
    expect(router.state.location.pathname).toBe('/monitors/7/edit')
    const nameField = within(form).getByLabelText('Name')
    expect(nameField).toHaveValue('Shop API')

    await user.clear(nameField)
    await user.type(nameField, 'Shop API v2')
    await user.click(within(form).getByRole('button', { name: 'Save changes' }))

    await waitFor(() => expect(monitorsApi.updateMonitor).toHaveBeenCalledWith(7, expect.objectContaining({ name: 'Shop API v2' })))
    expect(await screen.findByText('Changes saved.')).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/monitors/7')
  })

  it('searches monitors and keeps the search in the address', async () => {
    vi.mocked(monitorsApi.listMonitors).mockResolvedValue(paginated([makeMonitor()]))
    const user = userEvent.setup()
    const { router } = renderApp('/monitors')

    await screen.findAllByRole('link', { name: 'Shop API' })
    vi.mocked(monitorsApi.listMonitors).mockResolvedValue(paginated([]))
    await user.type(screen.getByRole('searchbox', { name: 'Search monitors' }), 'nothing')

    expect(await screen.findByText('No monitors match “nothing”.')).toBeInTheDocument()
    expect(monitorsApi.listMonitors).toHaveBeenLastCalledWith({ page: 1, status: undefined, search: 'nothing' })
    expect(router.state.location.search).toBe('?q=nothing')
  })

  it('pauses the selected monitors in bulk', async () => {
    vi.mocked(monitorsApi.listMonitors).mockResolvedValue(paginated([makeMonitor({ id: 1, name: 'Shop API' }), makeMonitor({ id: 2, name: 'Docs' })]))
    vi.mocked(monitorsApi.bulkMonitorAction).mockResolvedValue(2)
    const user = userEvent.setup()
    renderApp('/monitors')

    await screen.findAllByRole('link', { name: 'Docs' })
    await user.click(screen.getByRole('checkbox', { name: 'Select all monitors on this page' }))
    const bar = within(screen.getByRole('region', { name: '2 selected' }))
    await user.click(bar.getByRole('button', { name: 'Pause' }))

    expect(await screen.findByText('2 monitors updated.')).toBeInTheDocument()
    expect(monitorsApi.bulkMonitorAction).toHaveBeenCalledWith('pause', [1, 2])
  })

  it('asks before deleting monitors in bulk', async () => {
    vi.mocked(monitorsApi.listMonitors).mockResolvedValue(paginated([makeMonitor({ id: 1, name: 'Shop API' }), makeMonitor({ id: 2, name: 'Docs' })]))
    vi.mocked(monitorsApi.bulkMonitorAction).mockResolvedValue(1)
    const user = userEvent.setup()
    renderApp('/monitors')

    await user.click(await screen.findByRole('checkbox', { name: 'Select Docs' }))
    await user.click(within(screen.getByRole('region', { name: '1 selected' })).getByRole('button', { name: 'Delete' }))
    const dialog = within(await screen.findByRole('dialog'))
    await user.click(dialog.getByRole('button', { name: 'Delete' }))

    await waitFor(() => expect(monitorsApi.bulkMonitorAction).toHaveBeenCalledWith('delete', [2]))
  })

  it('does not offer bulk selection to members', async () => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(makeUser())
    vi.mocked(monitorsApi.listMonitors).mockResolvedValue(paginated([makeMonitor()]))
    renderApp('/monitors')

    await screen.findAllByRole('link', { name: 'Shop API' })
    expect(screen.queryByRole('checkbox')).not.toBeInTheDocument()
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
