import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as authApi from '../../api/auth.ts'
import * as dashboardApi from '../../api/dashboard.ts'
import type { User } from '../../api/types.ts'
import { axiosError } from '../../test/axiosErrors.ts'
import { renderApp } from '../../test/renderApp.tsx'

vi.mock('../../api/auth.ts')
vi.mock('../../api/dashboard.ts')

const ada: User = {
  id: 1,
  name: 'Ada Lovelace',
  email: 'ada@example.com',
  email_verified_at: null,
  created_at: '2026-10-17T12:00:00Z',
}

describe('authentication', () => {
  beforeEach(() => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(null)
    vi.mocked(dashboardApi.getAppConfig).mockResolvedValue({
      registration_enabled: true,
      monitors: { allowed_intervals: [1, 5, 10], default_interval: 5, timeout_min: 1, timeout_max: 30, timeout_default: 10, max_per_user: 20 },
    })
  })

  it('explains that registration is closed and hides the sign-up link', async () => {
    vi.mocked(dashboardApi.getAppConfig).mockResolvedValue({
      registration_enabled: false,
      monitors: { allowed_intervals: [1, 5, 10], default_interval: 5, timeout_min: 1, timeout_max: 30, timeout_default: 10, max_per_user: 20 },
    })
    renderApp('/register')

    expect(await screen.findByText('Registration is closed on this PulseCheck instance. Ask its administrator for an account.')).toBeInTheDocument()
    expect(screen.queryByLabelText('Password')).not.toBeInTheDocument()
  })

  it('redirects a guest from a protected route to the login page', async () => {
    const { router } = renderApp('/monitors')

    expect(await screen.findByRole('heading', { name: 'Log in' })).toBeInTheDocument()
    await waitFor(() => expect(router.state.location.pathname).toBe('/login'))
  })

  it('logs in and goes to the dashboard', async () => {
    vi.mocked(authApi.login).mockResolvedValue(ada)
    const { router } = renderApp('/login')
    const visitedPaths: string[] = []
    router.subscribe((state) => visitedPaths.push(state.location.pathname))
    const user = userEvent.setup()

    await user.type(await screen.findByLabelText('Email'), 'ada@example.com')
    await user.type(screen.getByLabelText('Password'), 'correct-horse-42')
    await user.click(screen.getByRole('button', { name: 'Log in' }))

    expect(await screen.findByRole('heading', { name: 'Dashboard' })).toBeInTheDocument()
    expect(visitedPaths).not.toContain('/login')
    await waitFor(() => expect(router.state.location.pathname).toBe('/dashboard'))
    expect(authApi.login).toHaveBeenCalledWith({ email: 'ada@example.com', password: 'correct-horse-42' }, expect.anything())
  })

  it('returns to the originally requested page after logging in', async () => {
    vi.mocked(authApi.login).mockResolvedValue(ada)
    const { router } = renderApp('/settings')
    const user = userEvent.setup()

    await user.type(await screen.findByLabelText('Email'), 'ada@example.com')
    await user.type(screen.getByLabelText('Password'), 'correct-horse-42')
    await user.click(screen.getByRole('button', { name: 'Log in' }))

    await waitFor(() => expect(router.state.location.pathname).toBe('/settings'))
  })

  it('shows the field error from the API when credentials are wrong', async () => {
    vi.mocked(authApi.login).mockRejectedValue(
      axiosError(422, {
        message: 'These credentials do not match our records.',
        errors: { email: ['These credentials do not match our records.'] },
      }),
    )
    const { router } = renderApp('/login')
    const user = userEvent.setup()

    await user.type(await screen.findByLabelText('Email'), 'ada@example.com')
    await user.type(screen.getByLabelText('Password'), 'wrong')
    await user.click(screen.getByRole('button', { name: 'Log in' }))

    expect(await screen.findByText('These credentials do not match our records.')).toBeInTheDocument()
    expect(screen.getByLabelText('Email')).toHaveAttribute('aria-invalid', 'true')
    await waitFor(() => expect(router.state.location.pathname).toBe('/login'))
  })

  it('shows a generic message when the server fails', async () => {
    vi.mocked(authApi.login).mockRejectedValue(axiosError(500))
    renderApp('/login')
    const user = userEvent.setup()

    await user.type(await screen.findByLabelText('Email'), 'ada@example.com')
    await user.type(screen.getByLabelText('Password'), 'whatever')
    await user.click(screen.getByRole('button', { name: 'Log in' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Something went wrong on our side. Try again in a moment.')
  })

  it('disables the button while logging in to prevent double submission', async () => {
    vi.mocked(authApi.login).mockReturnValue(new Promise(() => {}))
    renderApp('/login')
    const user = userEvent.setup()

    await user.type(await screen.findByLabelText('Email'), 'ada@example.com')
    await user.type(screen.getByLabelText('Password'), 'correct-horse-42')
    await user.click(screen.getByRole('button', { name: 'Log in' }))

    const button = await screen.findByRole('button', { name: 'Logging in…' })
    expect(button).toBeDisabled()
    await user.click(button)
    expect(authApi.login).toHaveBeenCalledTimes(1)
  })

  it('sends a logged-in user away from the login page', async () => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(ada)
    const { router } = renderApp('/login')

    expect(await screen.findByRole('heading', { name: 'Dashboard' })).toBeInTheDocument()
    await waitFor(() => expect(router.state.location.pathname).toBe('/dashboard'))
  })

  it('logs out and returns to the login page', async () => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(ada)
    vi.mocked(authApi.logout).mockResolvedValue()
    const { router } = renderApp('/dashboard')
    const user = userEvent.setup()

    const [logOutButton] = await screen.findAllByRole('button', { name: 'Log out' })
    await user.click(logOutButton)

    expect(await screen.findByRole('heading', { name: 'Log in' })).toBeInTheDocument()
    await waitFor(() => expect(router.state.location.pathname).toBe('/login'))
  })

  it('resets the password with the token from the link and confirms on the login page', async () => {
    vi.mocked(authApi.resetPassword).mockResolvedValue()
    renderApp('/reset-password?token=abc123&email=ada%40example.com')
    const user = userEvent.setup()

    expect(await screen.findByLabelText('Email')).toHaveValue('ada@example.com')
    await user.type(screen.getByLabelText('Password'), 'new-password-42')
    await user.type(screen.getByLabelText('Confirm password'), 'new-password-42')
    await user.click(screen.getByRole('button', { name: 'Save new password' }))

    expect(await screen.findByText('Password updated. Log in with your new password.')).toBeInTheDocument()
    expect(authApi.resetPassword).toHaveBeenCalledWith(
      { token: 'abc123', email: 'ada@example.com', password: 'new-password-42', password_confirmation: 'new-password-42' },
      expect.anything(),
    )
  })

  it('explains an incomplete reset link', async () => {
    renderApp('/reset-password')

    expect(await screen.findByText('This reset link is incomplete. Request a new one.')).toBeInTheDocument()
  })
})
