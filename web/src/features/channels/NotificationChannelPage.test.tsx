import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as authApi from '../../api/auth.ts'
import * as channelsApi from '../../api/notificationChannels.ts'
import * as teamsApi from '../../api/teams.ts'
import type { AlertPreview } from '../../api/types.ts'
import { axiosError } from '../../test/axiosErrors.ts'
import { ada, makeChannel, makeTeam, makeUser } from '../../test/fixtures.ts'
import { renderApp } from '../../test/renderApp.tsx'

vi.mock('../../api/auth.ts')
vi.mock('../../api/notificationChannels.ts')
vi.mock('../../api/teams.ts')

const ALL_EVENTS = ['down', 'recovered', 'degraded', 'degradation_resolved', 'reminder', 'closed']

const downPreview: AlertPreview = {
  event: 'monitor.down',
  severity: 'critical',
  title: '🔴 Example API is DOWN',
  summary: null,
  fields: [
    { label: 'Host', value: 'api.example.com' },
    { label: 'Cause', value: 'HTTP 503' },
  ],
}

describe('NotificationChannelPage', () => {
  beforeEach(() => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(ada)
    vi.mocked(channelsApi.listNotificationChannels).mockResolvedValue([])
    vi.mocked(channelsApi.getAlertPreview).mockResolvedValue(downPreview)
    vi.mocked(teamsApi.listTeams).mockResolvedValue([])
  })

  it('sends members back to the channel list', async () => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(makeUser())
    const { router } = renderApp('/channels/new')

    await screen.findByRole('heading', { name: 'Notification channels' })
    expect(router.state.location.pathname).toBe('/channels')
  })

  it('creates a channel with its delivery preferences and opens it for testing', async () => {
    vi.mocked(channelsApi.createNotificationChannel).mockResolvedValue(makeChannel({ id: 9, type: 'slack', name: 'Team' }))
    vi.mocked(channelsApi.listNotificationChannels).mockResolvedValue([makeChannel({ id: 9, type: 'slack', name: 'Team' })])
    const user = userEvent.setup()
    const { router } = renderApp('/channels/new')

    await user.selectOptions(await screen.findByLabelText('Type'), 'slack')
    await user.type(screen.getByLabelText('Name'), 'Team')
    await user.type(screen.getByLabelText('Webhook URL'), 'https://hooks.slack.com/services/T/B/x')
    await user.click(screen.getByRole('checkbox', { name: 'Reminder while still down' }))
    await user.click(screen.getByRole('checkbox', { name: 'Pause alerts during a time window' }))
    await user.click(screen.getByRole('checkbox', { name: 'Still send down alerts and reminders' }))
    await user.click(screen.getByRole('radio', { name: /Compact/ }))
    await user.selectOptions(screen.getByLabelText('Mention'), 'here')
    await user.click(screen.getByRole('button', { name: 'Add channel' }))

    expect(await screen.findByText('Channel added.')).toBeInTheDocument()
    expect(channelsApi.createNotificationChannel).toHaveBeenCalledWith({
      type: 'slack',
      name: 'Team',
      enabled: true,
      is_default: true,
      events: ['down', 'recovered', 'degraded', 'degradation_resolved', 'closed'],
      quiet_hours_start: '22:00',
      quiet_hours_end: '07:00',
      quiet_hours_allow_critical: false,
      message_format: 'compact',
      config: { webhook_url: 'https://hooks.slack.com/services/T/B/x', mention: 'here' },
      team_ids: [],
    })
    expect(router.state.location.pathname).toBe('/channels/9')
  })

  it('assigns the teams picked in the form to a new channel', async () => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(makeUser({ role: 'manager', team_ids: [1, 2] }))
    vi.mocked(teamsApi.listTeams).mockResolvedValue([makeTeam({ id: 1, name: 'Platform' }), makeTeam({ id: 2, name: 'Commerce' })])
    vi.mocked(channelsApi.createNotificationChannel).mockResolvedValue(makeChannel({ type: 'email' }))
    const user = userEvent.setup()
    renderApp('/channels/new')

    await user.selectOptions(await screen.findByLabelText('Type'), 'email')
    await user.type(screen.getByLabelText('Name'), 'Ops')
    await user.type(screen.getByLabelText('Email address'), 'ops@example.com')
    await user.click(await screen.findByRole('checkbox', { name: 'Commerce' }))
    await user.click(screen.getByRole('button', { name: 'Add channel' }))

    expect(await screen.findByText('Channel added.')).toBeInTheDocument()
    expect(channelsApi.createNotificationChannel).toHaveBeenCalledWith(expect.objectContaining({ team_ids: [2] }))
  })

  it('creates a webhook channel with custom headers', async () => {
    vi.mocked(channelsApi.createNotificationChannel).mockResolvedValue(makeChannel({ type: 'webhook' }))
    const user = userEvent.setup()
    renderApp('/channels/new')

    await user.selectOptions(await screen.findByLabelText('Type'), 'webhook')
    await user.type(screen.getByLabelText('Name'), 'PagerDuty')
    await user.type(screen.getByLabelText('URL'), 'https://events.example.com/in')
    await user.click(screen.getByRole('button', { name: 'Add header' }))
    await user.type(screen.getByLabelText('Header name'), 'Authorization')
    await user.type(screen.getByLabelText('Header value'), 'Bearer abc')
    await user.click(screen.getByRole('button', { name: 'Add channel' }))

    expect(await screen.findByText('Channel added.')).toBeInTheDocument()
    expect(channelsApi.createNotificationChannel).toHaveBeenCalledWith(expect.objectContaining({
      type: 'webhook',
      events: ALL_EVENTS,
      quiet_hours_start: null,
      quiet_hours_end: null,
      message_format: 'detailed',
      config: { url: 'https://events.example.com/in', headers: { Authorization: 'Bearer abc' } },
    }))
  })

  it('shows validation errors next to the field', async () => {
    vi.mocked(channelsApi.createNotificationChannel).mockRejectedValue(
      axiosError(422, {
        message: 'Invalid.',
        errors: { 'config.webhook_url': ['The config.webhook_url format is invalid.'], events: ['Pick at least one event.'] },
      }),
    )
    const user = userEvent.setup()
    renderApp('/channels/new')

    await user.selectOptions(await screen.findByLabelText('Type'), 'slack')
    await user.type(screen.getByLabelText('Name'), 'Team')
    await user.type(screen.getByLabelText('Webhook URL'), 'https://example.com')
    await user.click(screen.getByRole('button', { name: 'Add channel' }))

    expect(await screen.findByText('The config.webhook_url format is invalid.')).toBeInTheDocument()
    expect(screen.getByLabelText('Webhook URL')).toHaveAttribute('aria-invalid', 'true')
    expect(screen.getByText('Pick at least one event.')).toBeInTheDocument()
  })

  it('edits a channel without resending the saved secret and keeps its preferences', async () => {
    vi.mocked(channelsApi.listNotificationChannels).mockResolvedValue([
      makeChannel({
        id: 5,
        name: 'My phone',
        config: { chat_id: '123456789', silent: true },
        events: ['down', 'recovered'],
        quiet_hours_start: '23:00',
        quiet_hours_end: '06:30',
      }),
    ])
    vi.mocked(channelsApi.updateNotificationChannel).mockResolvedValue(makeChannel({ id: 5 }))
    const user = userEvent.setup()
    const { router } = renderApp('/channels/5')

    expect(await screen.findByRole('heading', { name: 'Edit My phone' })).toBeInTheDocument()
    expect(screen.queryByLabelText('Type')).not.toBeInTheDocument()
    expect(screen.getByLabelText('Bot token')).toHaveAttribute('placeholder', 'Saved. Type a new value to replace it.')
    expect(screen.getByRole('checkbox', { name: 'Send silently' })).toBeChecked()
    expect(screen.getByLabelText('From')).toHaveValue('23:00')

    const chatId = screen.getByLabelText('Chat ID')
    await user.clear(chatId)
    await user.type(chatId, '@status_channel')
    await user.click(screen.getByRole('button', { name: 'Save changes' }))

    expect(await screen.findByText('Changes saved.')).toBeInTheDocument()
    expect(channelsApi.updateNotificationChannel).toHaveBeenCalledWith(5, {
      name: 'My phone',
      enabled: true,
      is_default: true,
      events: ['down', 'recovered'],
      quiet_hours_start: '23:00',
      quiet_hours_end: '06:30',
      quiet_hours_allow_critical: true,
      message_format: 'detailed',
      config: { chat_id: '@status_channel', silent: true },
    })
    expect(router.state.location.pathname).toBe('/channels')
  })

  it('keeps saved webhook headers unless they are replaced', async () => {
    vi.mocked(channelsApi.listNotificationChannels).mockResolvedValue([
      makeChannel({ id: 6, name: 'Hook', type: 'webhook', config: { url: 'https://events.example.com/in' }, secrets_set: ['headers'] }),
    ])
    vi.mocked(channelsApi.updateNotificationChannel).mockResolvedValue(makeChannel({ id: 6 }))
    const user = userEvent.setup()
    renderApp('/channels/6')

    expect(await screen.findByText('Saved headers are hidden.')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Save changes' }))

    await screen.findByText('Changes saved.')
    expect(channelsApi.updateNotificationChannel).toHaveBeenLastCalledWith(6, expect.objectContaining({
      config: { url: 'https://events.example.com/in' },
    }))
  })

  it('clears a removed Discord role mention', async () => {
    vi.mocked(channelsApi.listNotificationChannels).mockResolvedValue([
      makeChannel({ id: 7, name: 'Guild', type: 'discord', config: { mention_role_id: '123456789012345678' }, secrets_set: ['webhook_url'] }),
    ])
    vi.mocked(channelsApi.updateNotificationChannel).mockResolvedValue(makeChannel({ id: 7 }))
    const user = userEvent.setup()
    renderApp('/channels/7')

    await user.clear(await screen.findByLabelText('Role to mention'))
    await user.click(screen.getByRole('button', { name: 'Save changes' }))

    await screen.findByText('Changes saved.')
    expect(channelsApi.updateNotificationChannel).toHaveBeenLastCalledWith(7, expect.objectContaining({
      config: { mention_role_id: null },
    }))
  })

  it('previews the alert with the mention and flags events the channel skips', async () => {
    vi.mocked(channelsApi.listNotificationChannels).mockResolvedValue([
      makeChannel({ id: 8, name: 'Team', type: 'slack', config: { mention: 'channel' }, secrets_set: ['webhook_url'], events: ['down'] }),
    ])
    const user = userEvent.setup()
    renderApp('/channels/8')

    const preview = await screen.findByTestId('alert-preview')
    expect(within(preview).getByText('🔴 Example API is DOWN')).toBeInTheDocument()
    expect(within(preview).getByText('@channel')).toBeInTheDocument()
    expect(within(preview).getByText('HTTP 503')).toBeInTheDocument()

    await user.click(screen.getByRole('radio', { name: /Compact/ }))
    expect(channelsApi.getAlertPreview).toHaveBeenLastCalledWith('down', 'compact')

    await user.selectOptions(screen.getByLabelText('Event'), 'recovered')
    expect(await screen.findByText('This channel does not receive this event.')).toBeInTheDocument()
  })

  it('sends a test message from the edit page only', async () => {
    vi.mocked(channelsApi.listNotificationChannels).mockResolvedValue([makeChannel({ id: 5, name: 'My phone' })])
    vi.mocked(channelsApi.sendNotificationChannelTest).mockResolvedValue('Test message sent.')
    const user = userEvent.setup()
    const { router } = renderApp('/channels/5')

    await user.click(await screen.findByRole('button', { name: 'Send test' }))
    expect(await screen.findByText('Test message sent.')).toBeInTheDocument()
    expect(channelsApi.sendNotificationChannelTest).toHaveBeenCalledWith(5)

    await router.navigate('/channels/new')
    expect(await screen.findByText('Save the channel to send a test message.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Send test' })).toBeDisabled()
  })

  it('shows a not found state for unknown channels', async () => {
    renderApp('/channels/404')

    expect(await screen.findByText('Channel not found')).toBeInTheDocument()
  })
})
