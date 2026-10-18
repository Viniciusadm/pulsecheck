import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as authApi from '../../api/auth.ts'
import * as configApi from '../../api/config.ts'
import * as monitorsApi from '../../api/monitors.ts'
import * as channelsApi from '../../api/notificationChannels.ts'
import { axiosError } from '../../test/axiosErrors.ts'
import { ada, makeConfig, makeChannel, makeMonitor, paginated } from '../../test/fixtures.ts'
import { renderApp } from '../../test/renderApp.tsx'

vi.mock('../../api/auth.ts')
vi.mock('../../api/monitors.ts')
vi.mock('../../api/notificationChannels.ts')

describe('MonitorFormDialog', () => {
  beforeEach(() => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(ada)
    vi.mocked(monitorsApi.listMonitors).mockResolvedValue(paginated([makeMonitor()]))
    vi.mocked(channelsApi.listNotificationChannels).mockResolvedValue([])
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
      { name: 'Docs', url: 'https://docs.example.com', interval_minutes: 5, timeout_seconds: 10 },
    )
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

  it('links to settings when there are no channels', async () => {
    const { dialog } = await openForm()

    expect(await within(dialog).findByText('No notification channels yet.')).toBeInTheDocument()
    expect(within(dialog).getByRole('link', { name: 'Manage channels' })).toHaveAttribute('href', '/settings')
  })
})
