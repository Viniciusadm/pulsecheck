import { useMutation, useQueryClient } from '@tanstack/react-query'
import { isAxiosError } from 'axios'
import { useState, type ChangeEvent, type FormEvent } from 'react'
import { fieldErrors, generalErrorMessage } from '../../api/errors.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import { completeSetup } from '../../api/setup.ts'
import type { AppConfig } from '../../api/types.ts'
import { Alert } from '../../components/ui/Alert.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { PasswordField } from '../../components/ui/PasswordField.tsx'
import { TextField } from '../../components/ui/TextField.tsx'
import { setLocale, t } from '../../i18n/index.ts'
import { useLocale } from '../../i18n/useLocale.ts'
import { browserTimeZone } from '../../lib/timeZones.ts'
import { useAuth } from '../auth/AuthContext.ts'
import { useAppConfig } from '../config/ConfigContext.ts'
import { LocaleSelect, TimeZoneSelect } from '../instance/InstanceFields.tsx'
import { NotificationChannelForm } from '../settings/NotificationChannelForm.tsx'

type Step = 1 | 2 | 3

const TOTAL_STEPS = 3
const INSTANCE_FIELDS = ['instance_name', 'timezone', 'locale']

export function SetupPage() {
  useLocale()
  const config = useAppConfig()
  const { setUser } = useAuth()
  const queryClient = useQueryClient()
  const [step, setStep] = useState<Step>(1)
  const [instance, setInstance] = useState({
    instance_name: '',
    timezone: browserTimeZone(),
    locale: config.instance.locale || 'en',
  })
  const [admin, setAdmin] = useState({ name: '', email: '', password: '', password_confirmation: '', token: '' })
  const [nameError, setNameError] = useState<string>()

  const mutation = useMutation({
    mutationFn: completeSetup,
    onSuccess: (user) => {
      setUser(user)
      setStep(3)
    },
    onError: (error) => {
      if (Object.keys(fieldErrors(error)).some((field) => INSTANCE_FIELDS.includes(field))) {
        setStep(1)
      }
    },
  })

  const errors = fieldErrors(mutation.error)
  const isAlreadySetUp = isAxiosError(mutation.error) && mutation.error.response?.status === 409
  const generalError = mutation.isError && !isAlreadySetUp ? generalErrorMessage(mutation.error) : null

  const changeLocale = (locale: string) => {
    setLocale(locale)
    setInstance((current) => ({ ...current, locale }))
  }

  const updateAdmin = (field: keyof typeof admin) => (event: ChangeEvent<HTMLInputElement>) =>
    setAdmin((current) => ({ ...current, [field]: event.target.value }))

  const submitInstance = (event: FormEvent) => {
    event.preventDefault()

    if (instance.instance_name.trim() === '') {
      setNameError(t('setup.instance.nameRequired'))

      return
    }

    setNameError(undefined)
    setStep(2)
  }

  const submitAdmin = (event: FormEvent) => {
    event.preventDefault()

    if (mutation.isPending) {
      return
    }

    const { token, ...account } = admin

    mutation.mutate({
      ...account,
      instance_name: instance.instance_name.trim(),
      timezone: instance.timezone,
      locale: instance.locale,
      ...(config.setup_token_required ? { token } : {}),
    })
  }

  const leaveSetup = (instanceSettings?: AppConfig['instance']) => {
    queryClient.setQueryData<AppConfig>(queryKeys.config, (current) =>
      current && {
        ...current,
        setup_required: false,
        setup_token_required: false,
        instance: instanceSettings ?? current.instance,
      },
    )
    void queryClient.invalidateQueries({ queryKey: queryKeys.config })
  }

  const finish = () =>
    leaveSetup({ name: instance.instance_name.trim(), timezone: instance.timezone, locale: instance.locale })

  if (isAlreadySetUp) {
    return (
      <div className="flex flex-col gap-6">
        <h1 className="text-2xl font-semibold text-neutral-900">{t('setup.title')}</h1>
        <Alert tone="info">{t('setup.alreadyDone')}</Alert>
        <div>
          <Button onClick={() => leaveSetup()}>{t('setup.goToLogin')}</Button>
        </div>
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col gap-1">
        <p className="text-sm font-medium text-primary-700">{t('setup.step', { current: step, total: TOTAL_STEPS })}</p>
        <h1 className="text-2xl font-semibold text-neutral-900">{t('setup.title')}</h1>
      </div>

      {step === 1 && (
        <form onSubmit={submitInstance} noValidate className="flex flex-col gap-4">
          <StepHeading title={t('setup.instance.heading')} description={t('setup.instance.description')} />
          <LocaleSelect
            label={t('setup.instance.language')}
            locales={config.available_locales}
            value={instance.locale}
            onChange={changeLocale}
            error={errors.locale}
          />
          <TextField
            label={t('setup.instance.name')}
            name="instance_name"
            required
            maxLength={100}
            placeholder={t('setup.instance.namePlaceholder')}
            value={instance.instance_name}
            onChange={(event) => setInstance((current) => ({ ...current, instance_name: event.target.value }))}
            error={nameError ?? errors.instance_name}
          />
          <TimeZoneSelect
            label={t('setup.instance.timezone')}
            hint={t('setup.instance.timezoneHint')}
            value={instance.timezone}
            onChange={(timezone) => setInstance((current) => ({ ...current, timezone }))}
            error={errors.timezone}
          />
          <Button type="submit">{t('setup.next')}</Button>
        </form>
      )}

      {step === 2 && (
        <form onSubmit={submitAdmin} noValidate className="flex flex-col gap-4">
          <StepHeading title={t('setup.admin.heading')} description={t('setup.admin.description')} />
          {generalError && <Alert tone="error">{generalError}</Alert>}
          <TextField label={t('auth.name')} name="name" autoComplete="name" required value={admin.name} onChange={updateAdmin('name')} error={errors.name} />
          <TextField
            label={t('auth.email')}
            type="email"
            name="email"
            autoComplete="email"
            required
            value={admin.email}
            onChange={updateAdmin('email')}
            error={errors.email}
          />
          <PasswordField
            label={t('auth.password')}
            name="password"
            autoComplete="new-password"
            required
            hint={t('auth.passwordHint')}
            value={admin.password}
            onChange={updateAdmin('password')}
            error={errors.password}
          />
          <PasswordField
            label={t('auth.passwordConfirmation')}
            name="password_confirmation"
            autoComplete="new-password"
            required
            value={admin.password_confirmation}
            onChange={updateAdmin('password_confirmation')}
            error={errors.password_confirmation}
          />
          {config.setup_token_required && (
            <PasswordField
              label={t('setup.admin.token')}
              name="token"
              autoComplete="off"
              required
              hint={t('setup.admin.tokenHint')}
              value={admin.token}
              onChange={updateAdmin('token')}
              error={errors.token}
            />
          )}
          <div className="flex flex-wrap justify-between gap-2">
            <Button variant="secondary" onClick={() => setStep(1)} disabled={mutation.isPending}>
              {t('setup.back')}
            </Button>
            <Button type="submit" isLoading={mutation.isPending} loadingLabel={t('setup.admin.submitting')}>
              {t('setup.admin.submit')}
            </Button>
          </div>
        </form>
      )}

      {step === 3 && (
        <div className="flex flex-col gap-4">
          <StepHeading title={t('setup.channel.heading')} description={t('setup.channel.description')} />
          <NotificationChannelForm
            onSaved={finish}
            onCancel={finish}
            cancelLabel={t('setup.channel.skip')}
            submitLabel={t('setup.channel.finish')}
            submittingLabel={t('setup.channel.finishing')}
          />
        </div>
      )}
    </div>
  )
}

function StepHeading({ title, description }: { title: string; description: string }) {
  return (
    <div className="flex flex-col gap-1">
      <h2 className="text-lg font-semibold text-neutral-900">{title}</h2>
      <p className="text-neutral-600">{description}</p>
    </div>
  )
}
