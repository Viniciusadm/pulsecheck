import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as authApi from '../../api/auth.ts'
import * as configApi from '../../api/config.ts'
import * as instanceApi from '../../api/instance.ts'
import * as channelsApi from '../../api/notificationChannels.ts'
import * as usersApi from '../../api/users.ts'
import { axiosError } from '../../test/axiosErrors.ts'
import { ada, makeConfig } from '../../test/fixtures.ts'
import { renderApp } from '../../test/renderApp.tsx'

vi.mock('../../api/auth.ts')
vi.mock('../../api/instance.ts')
vi.mock('../../api/notificationChannels.ts')
vi.mock('../../api/users.ts')

async function instanceSection() {
  return within((await screen.findByRole('heading', { name: 'Instance' })).closest('section')!)
}

describe('InstanceSection', () => {
  beforeEach(() => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(ada)
    vi.mocked(channelsApi.listNotificationChannels).mockResolvedValue([])
    vi.mocked(usersApi.listUsers).mockResolvedValue([ada])
    vi.mocked(configApi.getAppConfig).mockResolvedValue(
      makeConfig({ instance: { name: 'Acme', timezone: 'UTC', locale: 'en' } }),
    )
  })

  it('fills the form with the current instance settings', async () => {
    renderApp('/settings')
    const section = await instanceSection()

    expect(section.getByLabelText('Organization name')).toHaveValue('Acme')
    expect(section.getByLabelText('Time zone')).toHaveValue('UTC')
    expect(section.getByLabelText('Language')).toHaveValue('en')
  })

  it('saves the instance and shows the new name in the app', async () => {
    vi.mocked(instanceApi.updateInstance).mockResolvedValue({ name: 'Acme Ops', timezone: 'Europe/Lisbon', locale: 'en' })
    const user = userEvent.setup()
    renderApp('/settings')
    const section = await instanceSection()

    const name = section.getByLabelText('Organization name')
    await user.clear(name)
    await user.type(name, 'Acme Ops')
    await user.selectOptions(section.getByLabelText('Time zone'), 'Europe/Lisbon')
    await user.click(section.getByRole('button', { name: 'Save changes' }))

    expect(await screen.findByText('Instance settings saved.')).toBeInTheDocument()
    expect(instanceApi.updateInstance).toHaveBeenCalledWith({ name: 'Acme Ops', timezone: 'Europe/Lisbon', locale: 'en' }, expect.anything())
    expect((await screen.findAllByText('Acme Ops')).length).toBeGreaterThan(0)
    expect(document.title).toBe('Acme Ops')
  })

  it('shows validation errors from the API', async () => {
    vi.mocked(instanceApi.updateInstance).mockRejectedValue(
      axiosError(422, { message: 'Invalid.', errors: { name: ['The name field is required.'] } }),
    )
    const user = userEvent.setup()
    renderApp('/settings')
    const section = await instanceSection()

    await user.clear(section.getByLabelText('Organization name'))
    await user.click(section.getByRole('button', { name: 'Save changes' }))

    expect(await section.findByText('The name field is required.')).toBeInTheDocument()
  })
})
