import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as authApi from '../../api/auth.ts'
import * as dashboardApi from '../../api/dashboard.ts'
import { axiosError } from '../../test/axiosErrors.ts'
import { ada } from '../../test/fixtures.ts'
import { renderApp } from '../../test/renderApp.tsx'

vi.mock('../../api/auth.ts')
vi.mock('../../api/dashboard.ts')

async function submitCredentials() {
  const user = userEvent.setup()
  await user.type(await screen.findByLabelText('Email'), 'ada@example.com')
  await user.type(screen.getByLabelText('Password'), 'correct-horse-42')
  await user.click(screen.getByRole('button', { name: 'Log in' }))

  return user
}

describe('two-factor login', () => {
  beforeEach(() => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(null)
    vi.mocked(authApi.login).mockResolvedValue({ status: 'two-factor' })
    vi.mocked(dashboardApi.getDashboardSummary).mockResolvedValue({ total: 0, up: 0, degraded: 0, down: 0, pending: 0, maintenance: 0, paused: 0 })
    vi.mocked(dashboardApi.listRecentIncidents).mockResolvedValue([])
  })

  it('asks for the authentication code and logs in', async () => {
    vi.mocked(authApi.completeTwoFactorChallenge).mockResolvedValue(ada)
    const { router } = renderApp('/login')
    const user = await submitCredentials()

    expect(await screen.findByRole('heading', { name: 'Two-factor authentication' })).toBeInTheDocument()
    await user.type(screen.getByLabelText('Authentication code'), '123 456')
    await user.click(screen.getByRole('button', { name: 'Verify' }))

    await waitFor(() => expect(router.state.location.pathname).toBe('/dashboard'))
    expect(authApi.completeTwoFactorChallenge).toHaveBeenCalledWith({ code: '123456' }, expect.anything())
  })

  it('accepts a recovery code and shows errors from the API', async () => {
    vi.mocked(authApi.completeTwoFactorChallenge).mockRejectedValue(axiosError(422, { message: 'Invalid', errors: { recovery_code: ['The code is invalid or was already used.'] } }))
    renderApp('/login')
    const user = await submitCredentials()

    await user.click(await screen.findByRole('button', { name: 'Use a recovery code' }))
    await user.type(screen.getByLabelText('Recovery code'), ' abcde-fghij ')
    await user.click(screen.getByRole('button', { name: 'Verify' }))

    expect(await screen.findByText('The code is invalid or was already used.')).toBeInTheDocument()
    expect(authApi.completeTwoFactorChallenge).toHaveBeenCalledWith({ recovery_code: 'abcde-fghij' }, expect.anything())
  })

  it('goes back to the password step', async () => {
    renderApp('/login')
    const user = await submitCredentials()

    await user.click(await screen.findByRole('button', { name: 'Back to log in' }))

    expect(await screen.findByRole('heading', { name: 'Log in' })).toBeInTheDocument()
  })
})
