import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as authApi from '../../api/auth.ts'
import * as channelsApi from '../../api/notificationChannels.ts'
import { axiosError } from '../../test/axiosErrors.ts'
import { ada, makeChannel } from '../../test/fixtures.ts'
import { renderApp } from '../../test/renderApp.tsx'

vi.mock('../../api/auth.ts')
vi.mock('../../api/notificationChannels.ts')

describe('SettingsPage', () => {
  beforeEach(() => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(ada)
    vi.mocked(channelsApi.listNotificationChannels).mockResolvedValue([])
  })

  it('shows an empty state with a way to add a channel', async () => {
    renderApp('/settings')

    expect(await screen.findByText('No notification channels yet')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Add channel' })).toBeInTheDocument()
  })

  it('lists channels with their type, default flag, status and usage', async () => {
    vi.mocked(channelsApi.listNotificationChannels).mockResolvedValue([
      makeChannel({ id: 1, name: 'My phone' }),
      makeChannel({ id: 2, name: 'Ops mail', type: 'email', is_default: false, enabled: false, monitors_count: 1 }),
    ])
    renderApp('/settings')

    const phone = (await screen.findByText('My phone')).closest('li')!
    expect(within(phone).getByText('Telegram')).toBeInTheDocument()
    expect(within(phone).getByText('Default')).toBeInTheDocument()
    expect(within(phone).getByText('Used by 2 monitors')).toBeInTheDocument()

    const mail = screen.getByText('Ops mail').closest('li')!
    expect(within(mail).getByText('Email')).toBeInTheDocument()
    expect(within(mail).getByText('Disabled')).toBeInTheDocument()
    expect(within(mail).getByText('Used by 1 monitor')).toBeInTheDocument()
  })

  it('creates a webhook channel with custom headers', async () => {
    vi.mocked(channelsApi.createNotificationChannel).mockResolvedValue(makeChannel({ type: 'webhook' }))
    const user = userEvent.setup()
    renderApp('/settings')

    await user.click(await screen.findByRole('button', { name: 'Add channel' }))
    const dialog = await screen.findByRole('dialog', { name: 'Add notification channel' })
    await user.selectOptions(within(dialog).getByLabelText('Type'), 'webhook')
    await user.type(within(dialog).getByLabelText('Name'), 'PagerDuty')
    await user.type(within(dialog).getByLabelText('URL'), 'https://events.example.com/in')
    await user.click(within(dialog).getByRole('button', { name: 'Add header' }))
    await user.type(within(dialog).getByLabelText('Header name'), 'Authorization')
    await user.type(within(dialog).getByLabelText('Header value'), 'Bearer abc')
    await user.click(within(dialog).getByRole('button', { name: 'Add channel' }))

    expect(await screen.findByText('Channel added.')).toBeInTheDocument()
    expect(channelsApi.createNotificationChannel).toHaveBeenCalledWith({
      type: 'webhook',
      name: 'PagerDuty',
      enabled: true,
      is_default: true,
      config: { url: 'https://events.example.com/in', headers: { Authorization: 'Bearer abc' } },
    })
  })

  it('shows config validation errors next to the field', async () => {
    vi.mocked(channelsApi.createNotificationChannel).mockRejectedValue(
      axiosError(422, { message: 'Invalid.', errors: { 'config.webhook_url': ['The config.webhook_url format is invalid.'] } }),
    )
    const user = userEvent.setup()
    renderApp('/settings')

    await user.click(await screen.findByRole('button', { name: 'Add channel' }))
    const dialog = await screen.findByRole('dialog', { name: 'Add notification channel' })
    await user.selectOptions(within(dialog).getByLabelText('Type'), 'slack')
    await user.type(within(dialog).getByLabelText('Name'), 'Team')
    await user.type(within(dialog).getByLabelText('Webhook URL'), 'https://example.com')
    await user.click(within(dialog).getByRole('button', { name: 'Add channel' }))

    expect(await within(dialog).findByText('The config.webhook_url format is invalid.')).toBeInTheDocument()
    expect(within(dialog).getByLabelText('Webhook URL')).toHaveAttribute('aria-invalid', 'true')
  })

  it('edits a channel without resending the saved secret', async () => {
    vi.mocked(channelsApi.listNotificationChannels).mockResolvedValue([makeChannel({ id: 5, name: 'My phone' })])
    vi.mocked(channelsApi.updateNotificationChannel).mockResolvedValue(makeChannel({ id: 5 }))
    const user = userEvent.setup()
    renderApp('/settings')

    await user.click(await screen.findByRole('button', { name: 'Edit: My phone' }))
    const dialog = await screen.findByRole('dialog', { name: 'Edit My phone' })
    expect(within(dialog).queryByLabelText('Type')).not.toBeInTheDocument()
    expect(within(dialog).getByLabelText('Bot token')).toHaveAttribute('placeholder', 'Saved. Type a new value to replace it.')

    const chatId = within(dialog).getByLabelText('Chat ID')
    await user.clear(chatId)
    await user.type(chatId, '@status_channel')
    await user.click(within(dialog).getByRole('button', { name: 'Save changes' }))

    expect(await screen.findByText('Changes saved.')).toBeInTheDocument()
    expect(channelsApi.updateNotificationChannel).toHaveBeenCalledWith(5, {
      name: 'My phone',
      enabled: true,
      is_default: true,
      config: { chat_id: '@status_channel' },
    })
  })

  it('keeps saved webhook headers unless they are replaced', async () => {
    vi.mocked(channelsApi.listNotificationChannels).mockResolvedValue([
      makeChannel({ id: 6, name: 'Hook', type: 'webhook', config: { url: 'https://events.example.com/in' }, secrets_set: ['headers'] }),
    ])
    vi.mocked(channelsApi.updateNotificationChannel).mockResolvedValue(makeChannel({ id: 6 }))
    const user = userEvent.setup()
    renderApp('/settings')

    await user.click(await screen.findByRole('button', { name: 'Edit: Hook' }))
    const dialog = await screen.findByRole('dialog', { name: 'Edit Hook' })
    expect(within(dialog).getByText('Saved headers are hidden.')).toBeInTheDocument()
    await user.click(within(dialog).getByRole('button', { name: 'Save changes' }))

    await screen.findByText('Changes saved.')
    expect(channelsApi.updateNotificationChannel).toHaveBeenLastCalledWith(6, expect.objectContaining({
      config: { url: 'https://events.example.com/in' },
    }))
  })

  it('sends a test message and shows delivery errors from the API', async () => {
    vi.mocked(channelsApi.listNotificationChannels).mockResolvedValue([makeChannel({ id: 5, name: 'My phone' })])
    vi.mocked(channelsApi.sendNotificationChannelTest)
      .mockResolvedValueOnce('Test message sent.')
      .mockRejectedValueOnce(axiosError(422, { message: 'The destination rejected the notification. Check the channel settings.' }))
    const user = userEvent.setup()
    renderApp('/settings')

    const testButton = await screen.findByRole('button', { name: 'Send test: My phone' })
    await user.click(testButton)
    expect(await screen.findByText('Test message sent.')).toBeInTheDocument()

    await user.click(testButton)
    expect(await screen.findByText('The destination rejected the notification. Check the channel settings.')).toBeInTheDocument()
    expect(channelsApi.sendNotificationChannelTest).toHaveBeenCalledWith(5)
  })

  it('deletes a channel after confirmation', async () => {
    vi.mocked(channelsApi.listNotificationChannels).mockResolvedValue([makeChannel({ id: 5, name: 'My phone' })])
    vi.mocked(channelsApi.deleteNotificationChannel).mockResolvedValue()
    const user = userEvent.setup()
    renderApp('/settings')

    await user.click(await screen.findByRole('button', { name: 'Delete: My phone' }))
    const dialog = await screen.findByRole('dialog', { name: 'Delete My phone?' })
    await user.click(within(dialog).getByRole('button', { name: 'Delete channel' }))

    expect(await screen.findByText('Channel deleted.')).toBeInTheDocument()
    expect(channelsApi.deleteNotificationChannel).toHaveBeenCalledWith(5)
  })
})
