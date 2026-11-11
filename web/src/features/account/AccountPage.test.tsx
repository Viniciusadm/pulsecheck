import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as accountApi from '../../api/account.ts'
import * as authApi from '../../api/auth.ts'
import { axiosError } from '../../test/axiosErrors.ts'
import { ada } from '../../test/fixtures.ts'
import { renderApp } from '../../test/renderApp.tsx'

vi.mock('../../api/auth.ts')
vi.mock('../../api/account.ts')

function section(name: string) {
  return within(screen.getByRole('heading', { name, level: 2 }).closest('section')!)
}

describe('AccountPage', () => {
  beforeEach(() => {
    window.localStorage.clear()
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(ada)
    vi.mocked(accountApi.getTwoFactorStatus).mockResolvedValue({ enabled: false, pending_confirmation: false, confirmed_at: null, recovery_codes_remaining: 0 })
    vi.mocked(accountApi.listApiTokens).mockResolvedValue([])
  })

  it('saves the name and asks for the password only when the email changes', async () => {
    vi.mocked(accountApi.updateProfile).mockResolvedValue({ ...ada, name: 'Ada King' })
    renderApp('/account')
    const user = userEvent.setup()

    await screen.findByRole('heading', { name: 'My account' })
    const profile = section('Profile')
    expect(profile.queryByLabelText('Current password')).not.toBeInTheDocument()

    await user.clear(profile.getByLabelText('Name'))
    await user.type(profile.getByLabelText('Name'), 'Ada King')
    await user.click(profile.getByRole('button', { name: 'Save changes' }))

    await waitFor(() => expect(accountApi.updateProfile).toHaveBeenCalledWith({ name: 'Ada King', email: 'ada@example.com' }, expect.anything()))
    expect(await screen.findByText('Profile saved.')).toBeInTheDocument()

    await user.clear(profile.getByLabelText('Email'))
    await user.type(profile.getByLabelText('Email'), 'ada@new.example')
    expect(profile.getByLabelText('Current password')).toBeInTheDocument()
  })

  it('shows validation errors when changing the password', async () => {
    vi.mocked(accountApi.updatePassword).mockRejectedValue(axiosError(422, { message: 'Invalid', errors: { current_password: ['The password is incorrect.'] } }))
    renderApp('/account')
    const user = userEvent.setup()

    await screen.findByRole('heading', { name: 'My account' })
    const password = section('Password')
    await user.type(password.getByLabelText('Current password'), 'wrong')
    await user.type(password.getByLabelText('New password'), 'brand-new-42')
    await user.type(password.getByLabelText('Confirm password'), 'brand-new-42')
    await user.click(password.getByRole('button', { name: 'Change password' }))

    expect(await password.findByText('The password is incorrect.')).toBeInTheDocument()
  })

  it('switches the theme and remembers it', async () => {
    renderApp('/account')
    const user = userEvent.setup()

    await screen.findByRole('heading', { name: 'My account' })
    await user.click(section('Appearance').getByRole('radio', { name: 'Dark' }))

    expect(document.documentElement.dataset.theme).toBe('dark')
    expect(window.localStorage.getItem('pulsecheck.theme')).toBe('dark')
    expect(section('Appearance').getByRole('radio', { name: 'Dark' })).toHaveAttribute('aria-checked', 'true')

    await user.click(section('Appearance').getByRole('radio', { name: 'Light' }))
    expect(document.documentElement.dataset.theme).toBe('light')
  })

  it('turns on two-factor authentication and shows the recovery codes once', async () => {
    vi.mocked(accountApi.startTwoFactorSetup).mockResolvedValue({ secret: 'JBSWY3DPEHPK3PXP', otpauth_url: 'otpauth://totp/x', qr_code_svg: '<svg></svg>' })
    vi.mocked(accountApi.confirmTwoFactor).mockResolvedValue(['aaaaa-bbbbb', 'ccccc-ddddd'])
    renderApp('/account')
    const user = userEvent.setup()

    await screen.findByRole('heading', { name: 'My account' })
    await user.click(await section('Two-factor authentication').findByRole('button', { name: 'Turn on two-factor authentication' }))
    const dialog = within(await screen.findByRole('dialog'))
    await user.type(dialog.getByLabelText('Current password'), 'secret-pass-42')
    await user.click(dialog.getByRole('button', { name: 'Continue' }))

    expect(await screen.findByText('JBSWY3DPEHPK3PXP')).toBeInTheDocument()
    expect(screen.getByRole('img', { name: /QR code/ })).toBeInTheDocument()
    await user.type(screen.getByLabelText('Authentication code'), '123456')
    await user.click(screen.getByRole('button', { name: 'Turn on' }))

    const codes = await screen.findByRole('list', { name: 'Recovery codes' })
    expect(within(codes).getByText('aaaaa-bbbbb')).toBeInTheDocument()
    expect(accountApi.startTwoFactorSetup).toHaveBeenCalledWith('secret-pass-42', expect.anything())
    expect(accountApi.confirmTwoFactor).toHaveBeenCalledWith('123456', expect.anything())
  })

  it('creates an API token and shows it once', async () => {
    vi.mocked(accountApi.createApiToken).mockResolvedValue({
      token: { id: 1, name: 'Grafana', abilities: ['read'], last_used_at: null, expires_at: null, created_at: '2026-11-11T10:00:00Z' },
      plainTextToken: '1|pulse_secret',
    })
    renderApp('/account')
    const user = userEvent.setup()

    await screen.findByRole('heading', { name: 'My account' })
    await user.click(section('API tokens').getByRole('button', { name: 'Create token' }))
    const dialog = within(await screen.findByRole('dialog'))
    await user.type(dialog.getByLabelText('Name'), 'Grafana')
    await user.selectOptions(dialog.getByLabelText('Expires'), 'never')
    await user.click(dialog.getByRole('button', { name: 'Create token' }))

    expect(await screen.findByText('1|pulse_secret')).toBeInTheDocument()
    expect(accountApi.createApiToken).toHaveBeenCalledWith({ name: 'Grafana', abilities: ['read'], expires_in_days: null }, expect.anything())
  })
})
