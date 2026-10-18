import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as authApi from '../../api/auth.ts'
import * as configApi from '../../api/config.ts'
import * as dashboardApi from '../../api/dashboard.ts'
import * as monitorsApi from '../../api/monitors.ts'
import * as channelsApi from '../../api/notificationChannels.ts'
import * as setupApi from '../../api/setup.ts'
import * as teamsApi from '../../api/teams.ts'
import { axiosError } from '../../test/axiosErrors.ts'
import { ada, makeChannel, makeConfig, paginated } from '../../test/fixtures.ts'
import { renderApp } from '../../test/renderApp.tsx'

vi.mock('../../api/auth.ts')
vi.mock('../../api/dashboard.ts')
vi.mock('../../api/monitors.ts')
vi.mock('../../api/notificationChannels.ts')
vi.mock('../../api/setup.ts')
vi.mock('../../api/teams.ts')

const pendingConfig = makeConfig({ setup_required: true, instance: { name: '', timezone: 'UTC', locale: 'en' } })

function completeSetupSucceeds() {
  vi.mocked(setupApi.completeSetup).mockImplementation(async () => {
    vi.mocked(configApi.getAppConfig).mockResolvedValue(makeConfig({ instance: { name: 'Acme', timezone: 'America/Sao_Paulo', locale: 'en' } }))

    return ada
  })
}

async function fillInstanceStep(user: ReturnType<typeof userEvent.setup>) {
  await user.type(await screen.findByLabelText('Organization name'), 'Acme')
  await user.selectOptions(screen.getByLabelText('Time zone'), 'America/Sao_Paulo')
  await user.click(screen.getByRole('button', { name: 'Continue' }))
}

async function fillAdminStep(user: ReturnType<typeof userEvent.setup>) {
  await user.type(await screen.findByLabelText('Name'), 'Ada Lovelace')
  await user.type(screen.getByLabelText('Email'), 'ada@example.com')
  await user.type(screen.getByLabelText('Password'), 'correct-horse-42')
  await user.type(screen.getByLabelText('Confirm password'), 'correct-horse-42')
}

describe('setup', () => {
  beforeEach(() => {
    vi.mocked(configApi.getAppConfig).mockResolvedValue(pendingConfig)
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(null)
    vi.mocked(dashboardApi.getDashboardSummary).mockResolvedValue({ total: 0, up: 0, degraded: 0, down: 0, pending: 0, paused: 0 })
    vi.mocked(dashboardApi.listRecentIncidents).mockResolvedValue([])
    vi.mocked(monitorsApi.listMonitors).mockResolvedValue(paginated([]))
    vi.mocked(channelsApi.listNotificationChannels).mockResolvedValue([])
    vi.mocked(teamsApi.listTeams).mockResolvedValue([])
  })

  it.each(['/login', '/dashboard', '/settings'])('sends %s to the setup page while the instance is not set up', async (path) => {
    const { router } = renderApp(path)

    expect(await screen.findByRole('heading', { name: 'Set up PulseCheck' })).toBeInTheDocument()
    await waitFor(() => expect(router.state.location.pathname).toBe('/setup'))
  })

  it('sends the setup page to the login page once the instance is set up', async () => {
    vi.mocked(configApi.getAppConfig).mockResolvedValue(makeConfig())
    const { router } = renderApp('/setup')

    expect(await screen.findByRole('heading', { name: 'Log in' })).toBeInTheDocument()
    await waitFor(() => expect(router.state.location.pathname).toBe('/login'))
  })

  it('preselects the browser time zone and lists the available languages', async () => {
    renderApp('/setup')

    expect(await screen.findByLabelText('Time zone')).toHaveValue(Intl.DateTimeFormat().resolvedOptions().timeZone)
    const language = screen.getByLabelText('Language')
    expect(language).toHaveValue('en')
    expect(within(language).getByRole('option', { name: 'English' })).toBeInTheDocument()
  })

  it('asks for the instance name before moving on', async () => {
    const user = userEvent.setup()
    renderApp('/setup')

    await user.click(await screen.findByRole('button', { name: 'Continue' }))

    expect(screen.getByText('Enter a name for this instance.')).toBeInTheDocument()
    expect(screen.getByLabelText('Organization name')).toHaveAttribute('aria-invalid', 'true')
  })

  it('creates the instance and the administrator, then skips the channel step', async () => {
    completeSetupSucceeds()
    const user = userEvent.setup()
    const { router } = renderApp('/setup')

    await fillInstanceStep(user)
    expect(await screen.findByText('Step 2 of 3')).toBeInTheDocument()
    expect(screen.queryByLabelText('Setup token')).not.toBeInTheDocument()
    await fillAdminStep(user)
    await user.click(screen.getByRole('button', { name: 'Create administrator' }))

    expect(await screen.findByText('Step 3 of 3')).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/setup')
    expect(setupApi.completeSetup).toHaveBeenCalledWith(
      {
        instance_name: 'Acme',
        timezone: 'America/Sao_Paulo',
        locale: 'en',
        name: 'Ada Lovelace',
        email: 'ada@example.com',
        password: 'correct-horse-42',
        password_confirmation: 'correct-horse-42',
      },
      expect.anything(),
    )

    await user.click(screen.getByRole('button', { name: 'Skip for now' }))

    expect(await screen.findByRole('heading', { name: 'Dashboard' })).toBeInTheDocument()
    await waitFor(() => expect(router.state.location.pathname).toBe('/dashboard'))
    expect(channelsApi.createNotificationChannel).not.toHaveBeenCalled()
    expect(document.title).toBe('Acme')
  })

  it('adds a notification channel in the last step', async () => {
    completeSetupSucceeds()
    vi.mocked(channelsApi.createNotificationChannel).mockResolvedValue(makeChannel({ type: 'email', name: 'Ops' }))
    const user = userEvent.setup()
    const { router } = renderApp('/setup')

    await fillInstanceStep(user)
    await fillAdminStep(user)
    await user.click(screen.getByRole('button', { name: 'Create administrator' }))

    await user.selectOptions(await screen.findByLabelText('Type'), 'email')
    await user.type(screen.getByLabelText('Name'), 'Ops')
    await user.type(screen.getByLabelText('Email address'), 'ops@example.com')
    await user.click(screen.getByRole('button', { name: 'Add channel and finish' }))

    await waitFor(() => expect(router.state.location.pathname).toBe('/dashboard'))
    expect(channelsApi.createNotificationChannel).toHaveBeenCalledWith({
      type: 'email',
      name: 'Ops',
      enabled: true,
      is_default: true,
      config: { email: 'ops@example.com' },
      team_ids: [],
    })
  })

  it('reveals and hides the password on demand', async () => {
    const user = userEvent.setup()
    renderApp('/setup')

    await fillInstanceStep(user)
    const password = await screen.findByLabelText('Password')
    expect(password).toHaveAttribute('type', 'password')

    await user.click(screen.getAllByRole('button', { name: 'Show password' })[0])

    expect(password).toHaveAttribute('type', 'text')
    expect(screen.getByRole('button', { name: 'Hide password' })).toHaveAttribute('aria-pressed', 'true')

    await user.click(screen.getByRole('button', { name: 'Hide password' }))

    expect(password).toHaveAttribute('type', 'password')
  })

  it('asks for the setup token when the server requires one', async () => {
    vi.mocked(configApi.getAppConfig).mockResolvedValue({ ...pendingConfig, setup_token_required: true })
    vi.mocked(setupApi.completeSetup).mockRejectedValue(
      axiosError(422, { message: 'Invalid.', errors: { token: ['The setup token is invalid.'] } }),
    )
    const user = userEvent.setup()
    renderApp('/setup')

    await fillInstanceStep(user)
    await fillAdminStep(user)
    await user.type(screen.getByLabelText('Setup token'), 'wrong')
    await user.click(screen.getByRole('button', { name: 'Create administrator' }))

    expect(await screen.findByText('The setup token is invalid.')).toBeInTheDocument()
    expect(setupApi.completeSetup).toHaveBeenCalledWith(expect.objectContaining({ token: 'wrong' }), expect.anything())
  })

  it('returns to the first step when the API rejects an instance field', async () => {
    vi.mocked(setupApi.completeSetup).mockRejectedValue(
      axiosError(422, { message: 'Invalid.', errors: { timezone: ['The timezone field must be a valid timezone.'] } }),
    )
    const user = userEvent.setup()
    renderApp('/setup')

    await fillInstanceStep(user)
    await fillAdminStep(user)
    await user.click(screen.getByRole('button', { name: 'Create administrator' }))

    expect(await screen.findByText('The timezone field must be a valid timezone.')).toBeInTheDocument()
    expect(screen.getByText('Step 1 of 3')).toBeInTheDocument()
  })

  it('explains when the instance was set up meanwhile and leads to the login page', async () => {
    vi.mocked(setupApi.completeSetup).mockRejectedValue(axiosError(409, { message: 'Already set up.' }))
    const user = userEvent.setup()
    const { router } = renderApp('/setup')

    await fillInstanceStep(user)
    await fillAdminStep(user)
    await user.click(screen.getByRole('button', { name: 'Create administrator' }))

    expect(await screen.findByText('This PulseCheck instance is already set up. Log in with your account.')).toBeInTheDocument()
    vi.mocked(configApi.getAppConfig).mockResolvedValue(makeConfig())
    await user.click(screen.getByRole('button', { name: 'Go to log in' }))

    expect(await screen.findByRole('heading', { name: 'Log in' })).toBeInTheDocument()
    await waitFor(() => expect(router.state.location.pathname).toBe('/login'))
  })
})
