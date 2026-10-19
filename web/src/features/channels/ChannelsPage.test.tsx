import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as authApi from '../../api/auth.ts'
import * as channelsApi from '../../api/notificationChannels.ts'
import * as teamsApi from '../../api/teams.ts'
import { axiosError } from '../../test/axiosErrors.ts'
import { ada, makeChannel, makeUser } from '../../test/fixtures.ts'
import { renderApp } from '../../test/renderApp.tsx'

vi.mock('../../api/auth.ts')
vi.mock('../../api/notificationChannels.ts')
vi.mock('../../api/teams.ts')

describe('ChannelsPage', () => {
  beforeEach(() => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(ada)
    vi.mocked(channelsApi.listNotificationChannels).mockResolvedValue([])
    vi.mocked(teamsApi.listTeams).mockResolvedValue([])
  })

  it('shows channels read-only to members', async () => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(makeUser())
    vi.mocked(channelsApi.listNotificationChannels).mockResolvedValue([makeChannel({ id: 5, name: 'My phone' })])
    renderApp('/channels')

    expect(await screen.findByRole('heading', { name: 'Notification channels' })).toBeInTheDocument()
    expect(await screen.findByText('My phone')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Add channel' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit: My phone' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Send test: My phone' })).not.toBeInTheDocument()
  })

  it('lets managers manage channels', async () => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(makeUser({ role: 'manager' }))
    vi.mocked(channelsApi.listNotificationChannels).mockResolvedValue([makeChannel({ id: 5, name: 'My phone' })])
    renderApp('/channels')

    expect(await screen.findByRole('button', { name: 'Edit: My phone' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Add channel' })).toBeInTheDocument()
  })

  it('opens the dedicated pages to add and edit channels', async () => {
    vi.mocked(channelsApi.listNotificationChannels).mockResolvedValue([makeChannel({ id: 5, name: 'My phone' })])
    const user = userEvent.setup()
    const { router } = renderApp('/channels')

    await user.click(await screen.findByRole('button', { name: 'Edit: My phone' }))
    expect(router.state.location.pathname).toBe('/channels/5')

    await router.navigate('/channels')
    await user.click(await screen.findByRole('button', { name: 'Add channel' }))
    expect(router.state.location.pathname).toBe('/channels/new')
  })

  it('shows an empty state with a way to add a channel', async () => {
    renderApp('/channels')

    expect(await screen.findByText('No notification channels yet')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Add channel' })).toBeInTheDocument()
  })

  it('lists channels with their type, default flag, status and usage', async () => {
    vi.mocked(channelsApi.listNotificationChannels).mockResolvedValue([
      makeChannel({ id: 1, name: 'My phone' }),
      makeChannel({ id: 2, name: 'Ops mail', type: 'email', is_default: false, enabled: false, monitors_count: 1 }),
    ])
    renderApp('/channels')

    const phone = (await screen.findByText('My phone')).closest('li')!
    expect(within(phone).getByText('Telegram')).toBeInTheDocument()
    expect(within(phone).getByText('Default')).toBeInTheDocument()
    expect(within(phone).getByText('Used by 2 monitors')).toBeInTheDocument()

    const mail = screen.getByText('Ops mail').closest('li')!
    expect(within(mail).getByText('Email')).toBeInTheDocument()
    expect(within(mail).getByText('Disabled')).toBeInTheDocument()
    expect(within(mail).getByText('Used by 1 monitor')).toBeInTheDocument()
  })

  it('sends a test message and shows delivery errors from the API', async () => {
    vi.mocked(channelsApi.listNotificationChannels).mockResolvedValue([makeChannel({ id: 5, name: 'My phone' })])
    vi.mocked(channelsApi.sendNotificationChannelTest)
      .mockResolvedValueOnce('Test message sent.')
      .mockRejectedValueOnce(axiosError(422, { message: 'The destination rejected the notification. Check the channel settings.' }))
    const user = userEvent.setup()
    renderApp('/channels')

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
    renderApp('/channels')

    await user.click(await screen.findByRole('button', { name: 'Delete: My phone' }))
    const dialog = await screen.findByRole('dialog', { name: 'Delete My phone?' })
    await user.click(within(dialog).getByRole('button', { name: 'Delete channel' }))

    expect(await screen.findByText('Channel deleted.')).toBeInTheDocument()
    expect(channelsApi.deleteNotificationChannel).toHaveBeenCalledWith(5)
  })
})
