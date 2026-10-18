import { screen, within } from '@testing-library/react'
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

describe('MonitorFormDialog', () => {
  beforeEach(() => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(ada)
    vi.mocked(monitorsApi.listMonitors).mockResolvedValue(paginated([makeMonitor()]))
    vi.mocked(channelsApi.listNotificationChannels).mockResolvedValue([])
    vi.mocked(teamsApi.listTeams).mockResolvedValue([])
    vi.mocked(configApi.getAppConfig).mockResolvedValue(makeConfig())
  })

  async function openForm() {
    const user = userEvent.setup()
    renderApp('/monitors')
    await user.click(await screen.findByRole('button', { name: 'Add monitor' }))
    const dialog = await screen.findByRole('dialog', { name: 'Add monitor' })

    return { user, dialog }
  }

  it('offers the intervals from the API configuration', async () => {
    const { dialog } = await openForm()

    const select = within(dialog).getByLabelText('Check every')
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
    const { user, dialog } = await openForm()

    await user.type(within(dialog).getByLabelText('Name'), 'Router')
    await user.type(within(dialog).getByLabelText('URL'), 'http://192.168.0.1')
    await user.click(within(dialog).getByRole('button', { name: 'Add monitor' }))

    expect(await within(dialog).findByText('The url points to an address that is not allowed.')).toBeInTheDocument()
    expect(within(dialog).getByLabelText('URL')).toHaveAttribute('aria-invalid', 'true')
    expect(within(dialog).getByText('The timeout must be shorter than the check interval.')).toBeInTheDocument()
    expect(screen.getByRole('dialog')).toBeInTheDocument()
  })

  it('shows the monitor limit message', async () => {
    vi.mocked(monitorsApi.createMonitor).mockRejectedValue(
      axiosError(422, { message: 'Limit.', errors: { monitors: ['You have reached the limit of 20 monitors.'] } }),
    )
    const { user, dialog } = await openForm()

    await user.type(within(dialog).getByLabelText('Name'), 'One more')
    await user.type(within(dialog).getByLabelText('URL'), 'https://example.com')
    await user.click(within(dialog).getByRole('button', { name: 'Add monitor' }))

    expect(await within(dialog).findByRole('alert')).toHaveTextContent('You have reached the limit of 20 monitors.')
  })

  it('creates the monitor and opens its page', async () => {
    vi.mocked(monitorsApi.createMonitor).mockResolvedValue(makeMonitor({ id: 42, name: 'Docs' }))
    vi.mocked(monitorsApi.getMonitor).mockReturnValue(new Promise(() => {}))
    const { user, dialog } = await openForm()

    await user.type(within(dialog).getByLabelText('Name'), 'Docs')
    await user.type(within(dialog).getByLabelText('URL'), ' https://docs.example.com ')
    await user.click(within(dialog).getByRole('button', { name: 'Add monitor' }))

    expect(await screen.findByText('Monitor added. The first check runs within a minute.')).toBeInTheDocument()
    expect(monitorsApi.createMonitor).toHaveBeenCalledWith(
      { name: 'Docs', url: 'https://docs.example.com', interval_minutes: 5, timeout_seconds: 10, team_ids: [] },
    )
  })

  it('omits the timeout when the field is cleared', async () => {
    vi.mocked(monitorsApi.createMonitor).mockResolvedValue(makeMonitor({ id: 42 }))
    vi.mocked(monitorsApi.getMonitor).mockReturnValue(new Promise(() => {}))
    const { user, dialog } = await openForm()

    await user.type(within(dialog).getByLabelText('Name'), 'Docs')
    await user.type(within(dialog).getByLabelText('URL'), 'https://docs.example.com')
    await user.clear(within(dialog).getByLabelText('Timeout (seconds)'))
    await user.click(within(dialog).getByRole('button', { name: 'Add monitor' }))

    expect(monitorsApi.createMonitor).toHaveBeenCalledWith({ name: 'Docs', url: 'https://docs.example.com', interval_minutes: 5, team_ids: [] })
  })

  it('pre-selects the default channels and sends only an explicit choice', async () => {
    vi.mocked(channelsApi.listNotificationChannels).mockResolvedValue([
      makeChannel({ id: 1, name: 'My phone', is_default: true }),
      makeChannel({ id: 2, name: 'Team Slack', type: 'slack', is_default: false }),
    ])
    vi.mocked(monitorsApi.createMonitor).mockResolvedValue(makeMonitor({ id: 42 }))
    vi.mocked(monitorsApi.getMonitor).mockReturnValue(new Promise(() => {}))
    const { user, dialog } = await openForm()

    const phone = await within(dialog).findByRole('checkbox', { name: /My phone/ })
    const slack = within(dialog).getByRole('checkbox', { name: /Team Slack/ })
    expect(phone).toBeChecked()
    expect(slack).not.toBeChecked()

    await user.click(slack)
    await user.type(within(dialog).getByLabelText('Name'), 'Docs')
    await user.type(within(dialog).getByLabelText('URL'), 'https://docs.example.com')
    await user.click(within(dialog).getByRole('button', { name: 'Add monitor' }))

    await screen.findByText('Monitor added. The first check runs within a minute.')
    expect(monitorsApi.createMonitor).toHaveBeenCalledWith(expect.objectContaining({ notification_channel_ids: [1, 2] }))
  })

  it('links to the channels page when there are no channels', async () => {
    const { dialog } = await openForm()

    expect(await within(dialog).findByText('No notification channels yet.')).toBeInTheDocument()
    expect(within(dialog).getByRole('link', { name: 'Manage channels' })).toHaveAttribute('href', '/channels')
  })

  it('pre-selects the only team a manager belongs to', async () => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(makeUser({ role: 'manager', team_ids: [3] }))
    vi.mocked(teamsApi.listTeams).mockResolvedValue([makeTeam({ id: 3, name: 'Platform' })])
    vi.mocked(monitorsApi.createMonitor).mockResolvedValue(makeMonitor({ id: 42 }))
    vi.mocked(monitorsApi.getMonitor).mockReturnValue(new Promise(() => {}))
    const { user, dialog } = await openForm()

    expect(await within(dialog).findByRole('checkbox', { name: 'Platform' })).toBeChecked()
    await user.type(within(dialog).getByLabelText('Name'), 'Docs')
    await user.type(within(dialog).getByLabelText('URL'), 'https://docs.example.com')
    await user.click(within(dialog).getByRole('button', { name: 'Add monitor' }))

    await screen.findByText('Monitor added. The first check runs within a minute.')
    expect(monitorsApi.createMonitor).toHaveBeenCalledWith(expect.objectContaining({ team_ids: [3] }))
  })

  it('shows team validation errors', async () => {
    vi.mocked(teamsApi.listTeams).mockResolvedValue([makeTeam({ id: 3, name: 'Platform' }), makeTeam({ id: 4, name: 'Commerce' })])
    vi.mocked(monitorsApi.createMonitor).mockRejectedValue(
      axiosError(422, { message: 'Invalid.', errors: { team_ids: ['The team ids field is required.'] } }),
    )
    const { user, dialog } = await openForm()

    await user.type(within(dialog).getByLabelText('Name'), 'Docs')
    await user.type(within(dialog).getByLabelText('URL'), 'https://docs.example.com')
    await user.click(within(dialog).getByRole('button', { name: 'Add monitor' }))

    expect(await within(dialog).findByText('The team ids field is required.')).toBeInTheDocument()
  })

  it('sends request options, headers, body and authentication', async () => {
    vi.mocked(monitorsApi.createMonitor).mockResolvedValue(makeMonitor({ id: 42 }))
    vi.mocked(monitorsApi.getMonitor).mockReturnValue(new Promise(() => {}))
    const { user, dialog } = await openForm()

    await user.type(within(dialog).getByLabelText('Name'), 'API')
    await user.type(within(dialog).getByLabelText('URL'), 'https://api.example.com')
    await user.type(within(dialog).getByLabelText('Slow response limit (ms)'), '800')
    await user.click(within(dialog).getByText('Request options'))
    await user.selectOptions(within(dialog).getByLabelText('Method'), 'POST')
    await user.clear(within(dialog).getByLabelText('Expected status codes'))
    await user.type(within(dialog).getByLabelText('Expected status codes'), '200-299, 304')
    await user.click(within(dialog).getByRole('button', { name: 'Add header' }))
    await user.type(within(dialog).getByLabelText('Header name'), 'X-Api-Key')
    await user.type(within(dialog).getByLabelText('Header value'), 'abc')
    await user.type(within(dialog).getByLabelText('Content type'), 'application/json')
    await user.click(within(dialog).getByLabelText('Body'))
    await user.paste('{"ping":true}')
    await user.selectOptions(within(dialog).getByLabelText('Authentication'), 'bearer')
    await user.type(within(dialog).getByLabelText('Token'), 'secret')
    await user.click(within(dialog).getByRole('button', { name: 'Add monitor' }))

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
    const dialog = await screen.findByRole('dialog', { name: 'Edit monitor' })

    expect(within(dialog).getByText('Saved headers are hidden.')).toBeInTheDocument()
    expect(within(dialog).getByText('Authentication is saved. Fill in the fields only to replace it.')).toBeInTheDocument()
    await user.clear(within(dialog).getByLabelText('Name'))
    await user.type(within(dialog).getByLabelText('Name'), 'Renamed')
    await user.click(within(dialog).getByRole('button', { name: 'Save changes' }))

    await screen.findByText('Changes saved.')
    const input = vi.mocked(monitorsApi.updateMonitor).mock.calls[0][1]
    expect(input.name).toBe('Renamed')
    expect(input).not.toHaveProperty('options')
    expect(input).not.toHaveProperty('secrets')
    expect(input).not.toHaveProperty('latency_threshold_ms')
  })
})
