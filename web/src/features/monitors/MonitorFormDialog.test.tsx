import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as authApi from '../../api/auth.ts'
import * as dashboardApi from '../../api/dashboard.ts'
import * as monitorsApi from '../../api/monitors.ts'
import { axiosError } from '../../test/axiosErrors.ts'
import { ada, makeMonitor, paginated } from '../../test/fixtures.ts'
import { renderApp } from '../../test/renderApp.tsx'

vi.mock('../../api/auth.ts')
vi.mock('../../api/dashboard.ts')
vi.mock('../../api/monitors.ts')

describe('MonitorFormDialog', () => {
  beforeEach(() => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(ada)
    vi.mocked(monitorsApi.listMonitors).mockResolvedValue(paginated([makeMonitor()]))
    vi.mocked(dashboardApi.getAppConfig).mockResolvedValue({
      registration_enabled: true,
      monitors: { allowed_intervals: [1, 5, 10], default_interval: 5, timeout_min: 1, timeout_max: 30, timeout_default: 10, max_per_user: 20 },
    })
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
})
