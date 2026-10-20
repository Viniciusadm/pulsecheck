import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as authApi from '../../api/auth.ts'
import * as configApi from '../../api/config.ts'
import * as monitorsApi from '../../api/monitors.ts'
import * as channelsApi from '../../api/notificationChannels.ts'
import * as teamsApi from '../../api/teams.ts'
import { axiosError } from '../../test/axiosErrors.ts'
import { ada, emptyLatency, emptyUptimeDays, makeConfig, makeChannel, makeMonitor, makeStats, makeTeam, makeUser, paginated } from '../../test/fixtures.ts'
import { renderApp } from '../../test/renderApp.tsx'

vi.mock('../../api/auth.ts')
vi.mock('../../api/monitors.ts')
vi.mock('../../api/notificationChannels.ts')
vi.mock('../../api/teams.ts')

describe('MonitorFormPage', () => {
  beforeEach(() => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(ada)
    vi.mocked(monitorsApi.listMonitors).mockResolvedValue(paginated([makeMonitor()]))
    vi.mocked(channelsApi.listNotificationChannels).mockResolvedValue([])
    vi.mocked(teamsApi.listTeams).mockResolvedValue([])
    vi.mocked(configApi.getAppConfig).mockResolvedValue(makeConfig())
  })

  async function openForm() {
    const user = userEvent.setup()
    const { router } = renderApp('/monitors')
    await user.click(await screen.findByRole('button', { name: 'Add monitor' }))
    const form = await screen.findByRole('form', { name: 'Add monitor' })

    return { user, form, router }
  }

  it('offers the intervals from the API configuration', async () => {
    const { form } = await openForm()

    const select = within(form).getByLabelText('Check every')
    expect(await within(select).findAllByRole('option')).toHaveLength(3)
    expect(select).toHaveValue('5')
  })

  it('shows validation errors from the API next to each field', async () => {
    vi.mocked(monitorsApi.createMonitor).mockRejectedValue(
      axiosError(422, {
        message: 'Invalid data.',
        errors: {
          url: ['The url points to an address that is not allowed.'],
          timeout_seconds: ['The timeout must be shorter than the check interval.'],
        },
      }),
    )
    const { user, form, router } = await openForm()

    await user.type(within(form).getByLabelText('Name'), 'Router')
    await user.type(within(form).getByLabelText('URL'), 'http://192.168.0.1')
    await user.click(within(form).getByRole('button', { name: 'Add monitor' }))

    expect(await within(form).findByText('The url points to an address that is not allowed.')).toBeInTheDocument()
    expect(within(form).getByLabelText('URL')).toHaveAttribute('aria-invalid', 'true')
    expect(within(form).getByText('The timeout must be shorter than the check interval.')).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/monitors/new')
  })

  it('shows the monitor limit message', async () => {
    vi.mocked(monitorsApi.createMonitor).mockRejectedValue(
      axiosError(422, { message: 'Limit.', errors: { monitors: ['You have reached the limit of 20 monitors.'] } }),
    )
    const { user, form } = await openForm()

    await user.type(within(form).getByLabelText('Name'), 'One more')
    await user.type(within(form).getByLabelText('URL'), 'https://example.com')
    await user.click(within(form).getByRole('button', { name: 'Add monitor' }))

    expect(await within(form).findByRole('alert')).toHaveTextContent('You have reached the limit of 20 monitors.')
  })

  it('creates the monitor and opens its page', async () => {
    vi.mocked(monitorsApi.createMonitor).mockResolvedValue(makeMonitor({ id: 42, name: 'Docs' }))
    vi.mocked(monitorsApi.getMonitor).mockReturnValue(new Promise(() => {}))
    const { user, form, router } = await openForm()

    await user.type(within(form).getByLabelText('Name'), 'Docs')
    await user.type(within(form).getByLabelText('URL'), ' https://docs.example.com ')
    await user.click(within(form).getByRole('button', { name: 'Add monitor' }))

    expect(await screen.findByText('Monitor added. The first check runs within a minute.')).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/monitors/42')
    expect(monitorsApi.createMonitor).toHaveBeenCalledWith(
      { name: 'Docs', type: 'http', url: 'https://docs.example.com', interval_minutes: 5, timeout_seconds: 10, team_ids: [] },
    )
  })

  it('omits the timeout when the field is cleared', async () => {
    vi.mocked(monitorsApi.createMonitor).mockResolvedValue(makeMonitor({ id: 42 }))
    vi.mocked(monitorsApi.getMonitor).mockReturnValue(new Promise(() => {}))
    const { user, form } = await openForm()

    await user.type(within(form).getByLabelText('Name'), 'Docs')
    await user.type(within(form).getByLabelText('URL'), 'https://docs.example.com')
    await user.clear(within(form).getByLabelText('Timeout (seconds)'))
    await user.click(within(form).getByRole('button', { name: 'Add monitor' }))

    expect(monitorsApi.createMonitor).toHaveBeenCalledWith({ name: 'Docs', type: 'http', url: 'https://docs.example.com', interval_minutes: 5, team_ids: [] })
  })

  it('pre-selects the default channels and sends only an explicit choice', async () => {
    vi.mocked(channelsApi.listNotificationChannels).mockResolvedValue([
      makeChannel({ id: 1, name: 'My phone', is_default: true }),
      makeChannel({ id: 2, name: 'Team Slack', type: 'slack', is_default: false }),
    ])
    vi.mocked(monitorsApi.createMonitor).mockResolvedValue(makeMonitor({ id: 42 }))
    vi.mocked(monitorsApi.getMonitor).mockReturnValue(new Promise(() => {}))
    const { user, form } = await openForm()

    const phone = await within(form).findByRole('checkbox', { name: /My phone/ })
    const slack = within(form).getByRole('checkbox', { name: /Team Slack/ })
    expect(phone).toBeChecked()
    expect(slack).not.toBeChecked()

    await user.click(slack)
    await user.type(within(form).getByLabelText('Name'), 'Docs')
    await user.type(within(form).getByLabelText('URL'), 'https://docs.example.com')
    await user.click(within(form).getByRole('button', { name: 'Add monitor' }))

    await screen.findByText('Monitor added. The first check runs within a minute.')
    expect(monitorsApi.createMonitor).toHaveBeenCalledWith(expect.objectContaining({ notification_channel_ids: [1, 2] }))
  })

  it('pre-selects only the default channels that apply to the chosen teams', async () => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(makeUser({ role: 'manager', team_ids: [3] }))
    vi.mocked(teamsApi.listTeams).mockResolvedValue([makeTeam({ id: 3, name: 'Platform' })])
    vi.mocked(channelsApi.listNotificationChannels).mockResolvedValue([
      makeChannel({ id: 1, name: 'Platform phone', is_default: true, team_ids: [3] }),
      makeChannel({ id: 2, name: 'Billing Slack', type: 'slack', is_default: true, team_ids: [4] }),
    ])
    vi.mocked(monitorsApi.getMonitor).mockReturnValue(new Promise(() => {}))
    const { form } = await openForm()

    expect(await within(form).findByRole('checkbox', { name: /Platform phone/ })).toBeChecked()
    expect(within(form).getByRole('checkbox', { name: /Billing Slack/ })).not.toBeChecked()
  })

  it('links to the channels page when there are no channels', async () => {
    const { form } = await openForm()

    expect(await within(form).findByText('No notification channels yet.')).toBeInTheDocument()
    expect(within(form).getByRole('link', { name: 'Manage channels' })).toHaveAttribute('href', '/channels')
  })

  it('pre-selects the only team a manager belongs to', async () => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(makeUser({ role: 'manager', team_ids: [3] }))
    vi.mocked(teamsApi.listTeams).mockResolvedValue([makeTeam({ id: 3, name: 'Platform' })])
    vi.mocked(monitorsApi.createMonitor).mockResolvedValue(makeMonitor({ id: 42 }))
    vi.mocked(monitorsApi.getMonitor).mockReturnValue(new Promise(() => {}))
    const { user, form } = await openForm()

    expect(await within(form).findByRole('checkbox', { name: 'Platform' })).toBeChecked()
    await user.type(within(form).getByLabelText('Name'), 'Docs')
    await user.type(within(form).getByLabelText('URL'), 'https://docs.example.com')
    await user.click(within(form).getByRole('button', { name: 'Add monitor' }))

    await screen.findByText('Monitor added. The first check runs within a minute.')
    expect(monitorsApi.createMonitor).toHaveBeenCalledWith(expect.objectContaining({ team_ids: [3] }))
  })

  it('shows team validation errors', async () => {
    vi.mocked(teamsApi.listTeams).mockResolvedValue([makeTeam({ id: 3, name: 'Platform' }), makeTeam({ id: 4, name: 'Commerce' })])
    vi.mocked(monitorsApi.createMonitor).mockRejectedValue(
      axiosError(422, { message: 'Invalid.', errors: { team_ids: ['The team ids field is required.'] } }),
    )
    const { user, form } = await openForm()

    await user.type(within(form).getByLabelText('Name'), 'Docs')
    await user.type(within(form).getByLabelText('URL'), 'https://docs.example.com')
    await user.click(within(form).getByRole('button', { name: 'Add monitor' }))

    expect(await within(form).findByText('The team ids field is required.')).toBeInTheDocument()
  })

  it('sends request options, headers, body and authentication', async () => {
    vi.mocked(monitorsApi.createMonitor).mockResolvedValue(makeMonitor({ id: 42 }))
    vi.mocked(monitorsApi.getMonitor).mockReturnValue(new Promise(() => {}))
    const { user, form } = await openForm()

    await user.type(within(form).getByLabelText('Name'), 'API')
    await user.type(within(form).getByLabelText('URL'), 'https://api.example.com')
    await user.type(within(form).getByLabelText('Slow response limit (ms)'), '800')
    await user.click(within(form).getByText('Request options'))
    await user.selectOptions(within(form).getByLabelText('Method'), 'POST')
    await user.clear(within(form).getByLabelText('Expected status codes'))
    await user.type(within(form).getByLabelText('Expected status codes'), '200-299, 304')
    await user.click(within(form).getByRole('button', { name: 'Add header' }))
    await user.type(within(form).getByLabelText('Header name'), 'X-Api-Key')
    await user.type(within(form).getByLabelText('Header value'), 'abc')
    await user.type(within(form).getByLabelText('Content type'), 'application/json')
    await user.click(within(form).getByLabelText('Body'))
    await user.paste('{"ping":true}')
    await user.selectOptions(within(form).getByLabelText('Authentication'), 'bearer')
    await user.type(within(form).getByLabelText('Token'), 'secret')
    await user.click(within(form).getByRole('button', { name: 'Add monitor' }))

    await screen.findByText('Monitor added. The first check runs within a minute.')
    expect(monitorsApi.createMonitor).toHaveBeenCalledWith(expect.objectContaining({
      latency_threshold_ms: 800,
      options: { method: 'POST', follow_redirects: true, expected_status_codes: ['200-299', '304'], body_content_type: 'application/json' },
      secrets: { headers: { 'X-Api-Key': 'abc' }, body: '{"ping":true}', auth: { type: 'bearer', token: 'secret' } },
    }))
  })

  it('does not resend unchanged request options or saved secrets when editing', async () => {
    const monitor = makeMonitor({
      options: { method: 'POST', follow_redirects: true, expected_status_codes: ['200'], body_content_type: null },
      secrets_set: ['headers', 'auth'],
      auth_type: 'bearer',
    })
    vi.mocked(monitorsApi.getMonitor).mockResolvedValue(monitor)
    vi.mocked(monitorsApi.updateMonitor).mockResolvedValue(monitor)
    vi.mocked(monitorsApi.getMonitorStats).mockResolvedValue(makeStats())
    vi.mocked(monitorsApi.getLatencySeries).mockResolvedValue(emptyLatency)
    vi.mocked(monitorsApi.getUptimeHistory).mockResolvedValue(emptyUptimeDays)
    vi.mocked(monitorsApi.listMonitorIncidents).mockResolvedValue(paginated([]))
    vi.mocked(monitorsApi.listChecks).mockResolvedValue({ data: [], meta: { per_page: 50, next_cursor: null, prev_cursor: null } })
    const user = userEvent.setup()
    renderApp('/monitors/7')
    await user.click(await screen.findByRole('button', { name: 'Edit' }))
    const form = await screen.findByRole('form', { name: 'Edit monitor' })

    expect(within(form).getByText('Saved headers are hidden.')).toBeInTheDocument()
    expect(within(form).getByText('Authentication is saved. Fill in the fields only to replace it.')).toBeInTheDocument()
    await user.clear(within(form).getByLabelText('Name'))
    await user.type(within(form).getByLabelText('Name'), 'Renamed')
    await user.click(within(form).getByRole('button', { name: 'Save changes' }))

    await screen.findByText('Changes saved.')
    const input = vi.mocked(monitorsApi.updateMonitor).mock.calls[0][1]
    expect(input.name).toBe('Renamed')
    expect(input).not.toHaveProperty('options')
    expect(input).not.toHaveProperty('secrets')
    expect(input).not.toHaveProperty('latency_threshold_ms')
  })

  it('removes a saved body only when asked', async () => {
    const monitor = makeMonitor({
      options: { method: 'POST', follow_redirects: true, expected_status_codes: ['200'], body_content_type: null },
      secrets_set: ['body'],
    })
    vi.mocked(monitorsApi.getMonitor).mockResolvedValue(monitor)
    vi.mocked(monitorsApi.updateMonitor).mockResolvedValue(monitor)
    const user = userEvent.setup()
    renderApp('/monitors/7/edit')
    const form = await screen.findByRole('form', { name: 'Edit monitor' })

    await user.click(within(form).getByRole('button', { name: 'Remove saved body' }))
    expect(within(form).getByText('The saved body will be removed when you save.')).toBeInTheDocument()
    await user.click(within(form).getByRole('button', { name: 'Save changes' }))

    await screen.findByText('Changes saved.')
    const input = vi.mocked(monitorsApi.updateMonitor).mock.calls[0][1]
    expect(input.clear_secrets).toEqual(['body'])
    expect(input).not.toHaveProperty('secrets')
  })

  it('creates a TCP monitor without URL or request options', async () => {
    vi.mocked(monitorsApi.createMonitor).mockResolvedValue(makeMonitor({ id: 42 }))
    vi.mocked(monitorsApi.getMonitor).mockReturnValue(new Promise(() => {}))
    const { user, form } = await openForm()

    await user.selectOptions(within(form).getByLabelText('Type'), 'tcp')
    expect(within(form).queryByLabelText('URL')).not.toBeInTheDocument()
    expect(within(form).queryByText('Request options')).not.toBeInTheDocument()
    await user.type(within(form).getByLabelText('Name'), 'Database')
    await user.type(within(form).getByLabelText('Host'), 'db.example.com')
    await user.type(within(form).getByLabelText('Port'), '5432')
    await user.click(within(form).getByRole('button', { name: 'Add monitor' }))

    await screen.findByText('Monitor added. The first check runs within a minute.')
    expect(monitorsApi.createMonitor).toHaveBeenCalledWith({
      name: 'Database',
      type: 'tcp',
      interval_minutes: 5,
      timeout_seconds: 10,
      options: { host: 'db.example.com', port: 5432 },
      team_ids: [],
    })
  })

  it('offers heartbeat intervals and hides the timeout for heartbeat monitors', async () => {
    vi.mocked(monitorsApi.createMonitor).mockResolvedValue(makeMonitor({ id: 42 }))
    vi.mocked(monitorsApi.getMonitor).mockReturnValue(new Promise(() => {}))
    const { user, form } = await openForm()

    await user.selectOptions(within(form).getByLabelText('Type'), 'heartbeat')
    expect(within(form).queryByLabelText('Timeout (seconds)')).not.toBeInTheDocument()
    expect(within(within(form).getByLabelText('Expect a call every')).getByRole('option', { name: '24 h' })).toBeInTheDocument()
    await user.selectOptions(within(form).getByLabelText('Expect a call every'), '60')
    await user.type(within(form).getByLabelText('Name'), 'Nightly backup')
    await user.click(within(form).getByRole('button', { name: 'Add monitor' }))

    await screen.findByText('Monitor added. The first check runs within a minute.')
    expect(monitorsApi.createMonitor).toHaveBeenCalledWith({
      name: 'Nightly backup',
      type: 'heartbeat',
      interval_minutes: 60,
      options: { grace_seconds: 60 },
      team_ids: [],
    })
  })

  it('sends the reminder interval only when chosen', async () => {
    vi.mocked(monitorsApi.createMonitor).mockResolvedValue(makeMonitor({ id: 42 }))
    vi.mocked(monitorsApi.getMonitor).mockReturnValue(new Promise(() => {}))
    const { user, form } = await openForm()

    await user.type(within(form).getByLabelText('Name'), 'Docs')
    await user.type(within(form).getByLabelText('URL'), 'https://docs.example.com')
    await user.selectOptions(within(form).getByLabelText('Repeat the alert while down'), '30')
    await user.click(within(form).getByRole('button', { name: 'Add monitor' }))

    await screen.findByText('Monitor added. The first check runs within a minute.')
    expect(monitorsApi.createMonitor).toHaveBeenCalledWith(expect.objectContaining({ reminder_interval_minutes: 30 }))
  })

  it('goes back to the list when creating is cancelled', async () => {
    const { user, form, router } = await openForm()

    await user.click(within(form).getByRole('button', { name: 'Cancel' }))

    await waitFor(() => expect(router.state.location.pathname).toBe('/monitors'))
  })

  it('sends members without permission back to the list', async () => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(makeUser())
    const { router } = renderApp('/monitors/new')

    await waitFor(() => expect(router.state.location.pathname).toBe('/monitors'))
    expect(screen.queryByRole('form', { name: 'Add monitor' })).not.toBeInTheDocument()
  })

  it('shows not found when editing a missing monitor', async () => {
    vi.mocked(monitorsApi.getMonitor).mockRejectedValue(axiosError(404, { message: 'Not found.' }))
    renderApp('/monitors/99/edit')

    expect(await screen.findByText('This monitor does not exist or was deleted.')).toBeInTheDocument()
  })
})
