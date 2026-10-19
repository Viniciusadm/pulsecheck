import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as authApi from '../../api/auth.ts'
import * as configApi from '../../api/config.ts'
import * as mailApi from '../../api/mailSettings.ts'
import * as channelsApi from '../../api/notificationChannels.ts'
import * as usersApi from '../../api/users.ts'
import type { MailSettings } from '../../api/types.ts'
import { axiosError } from '../../test/axiosErrors.ts'
import { ada, makeConfig } from '../../test/fixtures.ts'
import { renderApp } from '../../test/renderApp.tsx'

vi.mock('../../api/auth.ts')
vi.mock('../../api/mailSettings.ts')
vi.mock('../../api/notificationChannels.ts')
vi.mock('../../api/users.ts')

const environmentSettings: MailSettings = {
  source: 'environment',
  environment_driver: 'log',
  driver: null,
  from_address: null,
  from_name: null,
  config: {},
  secrets_set: [],
}

const smtpSettings: MailSettings = {
  source: 'panel',
  environment_driver: 'log',
  driver: 'smtp',
  from_address: 'alerts@acme.test',
  from_name: 'Acme',
  config: { host: 'smtp.acme.test', port: 587, username: 'mailer' },
  secrets_set: ['password'],
}

async function mailSection() {
  return within((await screen.findByRole('heading', { name: 'Email' })).closest('section')!)
}

describe('MailSection', () => {
  beforeEach(() => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(ada)
    vi.mocked(channelsApi.listNotificationChannels).mockResolvedValue([])
    vi.mocked(usersApi.listUsers).mockResolvedValue([ada])
    vi.mocked(configApi.getAppConfig).mockResolvedValue(makeConfig())
    vi.mocked(mailApi.getMailSettings).mockResolvedValue(environmentSettings)
  })

  it('explains that the environment settings are in use', async () => {
    renderApp('/settings')
    const section = await mailSection()

    expect(await section.findByText(/defined in the environment variables \(log\)/)).toBeInTheDocument()
    expect(section.getByLabelText('Service')).toHaveValue('smtp')
    expect(section.queryByRole('button', { name: 'Send test email' })).not.toBeInTheDocument()
    expect(section.queryByRole('button', { name: 'Use environment settings' })).not.toBeInTheDocument()
  })

  it('shows the fields of the selected service', async () => {
    const user = userEvent.setup()
    renderApp('/settings')
    const section = await mailSection()

    expect(await section.findByLabelText('Host')).toBeInTheDocument()

    await user.selectOptions(section.getByLabelText('Service'), 'ses')

    expect(section.queryByLabelText('Host')).not.toBeInTheDocument()
    expect(section.getByLabelText('Access key ID')).toBeInTheDocument()
    expect(section.getByLabelText('Secret access key')).toBeInTheDocument()
    expect(section.getByLabelText('Region')).toHaveValue('us-east-1')
  })

  it('saves smtp settings and keeps the stored password when left blank', async () => {
    vi.mocked(mailApi.getMailSettings).mockResolvedValue(smtpSettings)
    vi.mocked(mailApi.updateMailSettings).mockResolvedValue({ ...smtpSettings, config: { ...smtpSettings.config, host: 'mail.acme.test' } })
    const user = userEvent.setup()
    renderApp('/settings')
    const section = await mailSection()

    expect(await section.findByText('Leave blank to keep the current value.')).toBeInTheDocument()
    const host = section.getByLabelText('Host')
    await user.clear(host)
    await user.type(host, 'mail.acme.test')
    await user.click(section.getByRole('button', { name: 'Save changes' }))

    expect(await screen.findByText('Mail settings saved.')).toBeInTheDocument()
    expect(mailApi.updateMailSettings).toHaveBeenCalledWith(
      {
        driver: 'smtp',
        from_address: 'alerts@acme.test',
        from_name: 'Acme',
        config: { host: 'mail.acme.test', port: 587, scheme: null, username: 'mailer', password: null },
      },
      expect.anything(),
    )
  })

  it('shows validation errors next to the fields', async () => {
    vi.mocked(mailApi.updateMailSettings).mockRejectedValue(
      axiosError(422, { message: 'Invalid.', errors: { 'config.host': ['The host field is required.'] } }),
    )
    const user = userEvent.setup()
    renderApp('/settings')
    const section = await mailSection()

    await user.click(await section.findByRole('button', { name: 'Save changes' }))

    expect(await section.findByText('The host field is required.')).toBeInTheDocument()
  })

  it('sends a test email and reports failures', async () => {
    vi.mocked(mailApi.getMailSettings).mockResolvedValue(smtpSettings)
    vi.mocked(mailApi.sendTestMail)
      .mockResolvedValueOnce('Test email sent to ada@example.com.')
      .mockRejectedValueOnce(axiosError(422, { message: 'The test email could not be sent.' }))
    const user = userEvent.setup()
    renderApp('/settings')
    const section = await mailSection()

    await user.click(await section.findByRole('button', { name: 'Send test email' }))
    expect(await screen.findByText('Test email sent to ada@example.com.')).toBeInTheDocument()

    await user.click(section.getByRole('button', { name: 'Send test email' }))
    expect(await section.findByText('The test email could not be sent.')).toBeInTheDocument()
  })

  it('goes back to the environment settings after confirming', async () => {
    vi.mocked(mailApi.getMailSettings).mockResolvedValueOnce(smtpSettings).mockResolvedValue(environmentSettings)
    vi.mocked(mailApi.deleteMailSettings).mockResolvedValue()
    const user = userEvent.setup()
    renderApp('/settings')
    const section = await mailSection()

    await user.click(await section.findByRole('button', { name: 'Use environment settings' }))
    expect(mailApi.deleteMailSettings).not.toHaveBeenCalled()
    await user.click(section.getByRole('button', { name: 'Remove settings' }))

    expect(await screen.findByText('Mail settings removed. Using the environment variables again.')).toBeInTheDocument()
    expect(mailApi.deleteMailSettings).toHaveBeenCalled()
    expect(await section.findByText(/defined in the environment variables/)).toBeInTheDocument()
  })
})
