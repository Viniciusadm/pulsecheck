import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { fieldErrors, generalErrorMessage } from '../../api/errors.ts'
import { deleteMailSettings, getMailSettings, sendTestMail, updateMailSettings } from '../../api/mailSettings.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import type { MailConfig, MailDriver, MailSettings } from '../../api/types.ts'
import { Alert } from '../../components/ui/Alert.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { Card } from '../../components/ui/Card.tsx'
import { ErrorState } from '../../components/ui/ErrorState.tsx'
import { PasswordField } from '../../components/ui/PasswordField.tsx'
import { SelectField } from '../../components/ui/SelectField.tsx'
import { Skeleton } from '../../components/ui/Skeleton.tsx'
import { TextField } from '../../components/ui/TextField.tsx'
import { useToast } from '../../components/ui/toast.ts'
import { t, type MessageKey } from '../../i18n/index.ts'
import { deliveryMessage } from '../channels/deliveryMessage.ts'

type MailField = {
  key: string
  label: MessageKey
  kind: 'text' | 'number' | 'secret' | 'select'
  isOptional?: boolean
  options?: { value: string; label: MessageKey }[]
}

const drivers: MailDriver[] = ['smtp', 'mailgun', 'postmark', 'resend', 'ses']

const driverFields: Record<MailDriver, MailField[]> = {
  smtp: [
    { key: 'host', label: 'settings.mail.fields.host', kind: 'text' },
    { key: 'port', label: 'settings.mail.fields.port', kind: 'number' },
    {
      key: 'scheme',
      label: 'settings.mail.fields.scheme',
      kind: 'select',
      options: [
        { value: '', label: 'settings.mail.options.schemeAuto' },
        { value: 'smtp', label: 'settings.mail.options.schemeStarttls' },
        { value: 'smtps', label: 'settings.mail.options.schemeTls' },
      ],
    },
    { key: 'username', label: 'settings.mail.fields.username', kind: 'text', isOptional: true },
    { key: 'password', label: 'settings.mail.fields.password', kind: 'secret', isOptional: true },
  ],
  mailgun: [
    { key: 'domain', label: 'settings.mail.fields.mailgunDomain', kind: 'text' },
    { key: 'secret', label: 'settings.mail.fields.mailgunSecret', kind: 'secret' },
    {
      key: 'endpoint',
      label: 'settings.mail.fields.mailgunEndpoint',
      kind: 'select',
      options: [
        { value: 'api.mailgun.net', label: 'settings.mail.options.mailgunUs' },
        { value: 'api.eu.mailgun.net', label: 'settings.mail.options.mailgunEu' },
      ],
    },
  ],
  postmark: [
    { key: 'token', label: 'settings.mail.fields.postmarkToken', kind: 'secret' },
    { key: 'message_stream_id', label: 'settings.mail.fields.postmarkStream', kind: 'text', isOptional: true },
  ],
  resend: [{ key: 'key', label: 'settings.mail.fields.resendKey', kind: 'secret' }],
  ses: [
    { key: 'key', label: 'settings.mail.fields.sesKey', kind: 'text' },
    { key: 'secret', label: 'settings.mail.fields.sesSecret', kind: 'secret' },
    { key: 'region', label: 'settings.mail.fields.sesRegion', kind: 'text' },
  ],
}

const driverDefaults: Record<MailDriver, Record<string, string>> = {
  smtp: { port: '587', scheme: '' },
  mailgun: { endpoint: 'api.mailgun.net' },
  postmark: {},
  resend: {},
  ses: { region: 'us-east-1' },
}

type MailFormState = {
  driver: MailDriver
  from_address: string
  from_name: string
  config: Record<string, string>
  clearedSecrets: string[]
}

function formFrom(settings: MailSettings): MailFormState {
  const driver = settings.driver ?? 'smtp'
  const config = Object.fromEntries(Object.entries(settings.config).map(([key, value]) => [key, value === null ? '' : String(value)]))

  return {
    driver,
    from_address: settings.from_address ?? '',
    from_name: settings.from_name ?? '',
    config: { ...driverDefaults[driver], ...config },
    clearedSecrets: [],
  }
}

function payloadConfig(form: MailFormState): MailConfig {
  return Object.fromEntries(
    driverFields[form.driver].map((field) => {
      const value = (form.config[field.key] ?? '').trim()

      if (value === '') {
        return [field.key, null]
      }

      return [field.key, field.kind === 'number' ? Number(value) : value]
    }),
  )
}

export function MailSection() {
  const settings = useQuery({ queryKey: queryKeys.mailSettings, queryFn: getMailSettings })

  return (
    <section aria-labelledby="mail-heading">
      <Card className="flex max-w-3xl flex-col gap-6 px-5 py-6 sm:px-6">
        <div className="flex max-w-prose flex-col gap-1">
          <h2 id="mail-heading" className="text-lg font-semibold text-neutral-900">{t('settings.mail.heading')}</h2>
          <p className="text-neutral-600">{t('settings.mail.description')}</p>
        </div>
        {settings.isPending && <Skeleton className="h-64" />}
        {settings.isError && <ErrorState onRetry={() => void settings.refetch()} />}
        {settings.data && <MailForm key={`${settings.data.source}-${settings.data.driver}`} settings={settings.data} />}
      </Card>
    </section>
  )
}

function MailForm({ settings }: { settings: MailSettings }) {
  const queryClient = useQueryClient()
  const { showToast } = useToast()
  const [form, setForm] = useState(() => formFrom(settings))
  const [isConfirmingReset, setIsConfirmingReset] = useState(false)

  const save = useMutation({
    mutationFn: updateMailSettings,
    onSuccess: (saved) => {
      showToast(t('settings.mail.saved'))
      queryClient.setQueryData(queryKeys.mailSettings, saved)
      setForm(formFrom(saved))
    },
  })

  const test = useMutation({
    mutationFn: sendTestMail,
    onSuccess: (message) => showToast(message),
  })

  const reset = useMutation({
    mutationFn: deleteMailSettings,
    onSuccess: () => {
      showToast(t('settings.mail.resetDone'))
      setIsConfirmingReset(false)
      void queryClient.invalidateQueries({ queryKey: queryKeys.mailSettings })
    },
  })

  const errors = fieldErrors(save.error)
  const generalError = save.isError ? generalErrorMessage(save.error) : null
  const isPanel = settings.source === 'panel'
  const savedSecrets = settings.driver === form.driver ? settings.secrets_set : []

  const changeDriver = (driver: MailDriver) => {
    const config = settings.driver === driver ? formFrom(settings).config : driverDefaults[driver]
    setForm({ ...form, driver, config, clearedSecrets: [] })
  }

  const changeConfig = (key: string, value: string) => {
    setForm({ ...form, config: { ...form.config, [key]: value } })
  }

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault()

    if (!save.isPending) {
      save.mutate({
        driver: form.driver,
        from_address: form.from_address.trim(),
        from_name: form.from_name.trim() || null,
        config: payloadConfig(form),
        ...(form.clearedSecrets.length > 0 ? { clear_secrets: form.clearedSecrets } : {}),
      })
    }
  }

  return (
    <form onSubmit={handleSubmit} noValidate className="flex max-w-md flex-col gap-4">
      <Alert tone="info">
        {isPanel ? t('settings.mail.panelNotice') : t('settings.mail.environmentNotice', { driver: settings.environment_driver })}
      </Alert>
      {generalError && <Alert tone="error">{generalError}</Alert>}
      <SelectField
        label={t('settings.mail.driver')}
        name="driver"
        value={form.driver}
        onChange={(event) => changeDriver(event.target.value as MailDriver)}
        error={errors.driver}
      >
        {drivers.map((driver) => (
          <option key={driver} value={driver}>
            {t(`settings.mail.drivers.${driver}`)}
          </option>
        ))}
      </SelectField>
      {driverFields[form.driver].map((field) => (
        <MailConfigField
          key={`${form.driver}-${field.key}`}
          field={field}
          value={form.config[field.key] ?? ''}
          isSecretSet={savedSecrets.includes(field.key)}
          isClearing={form.clearedSecrets.includes(field.key)}
          error={errors[`config.${field.key}`]}
          onChange={(value) => changeConfig(field.key, value)}
          onClearingChange={(isClearing) =>
            setForm({ ...form, clearedSecrets: isClearing ? [...form.clearedSecrets, field.key] : form.clearedSecrets.filter((key) => key !== field.key) })
          }
        />
      ))}
      <TextField
        label={t('settings.mail.fromAddress')}
        name="from_address"
        type="email"
        required
        maxLength={255}
        value={form.from_address}
        onChange={(event) => setForm({ ...form, from_address: event.target.value })}
        error={errors.from_address}
      />
      <TextField
        label={t('settings.mail.fromName')}
        name="from_name"
        maxLength={100}
        hint={t('settings.mail.fromNameHint')}
        value={form.from_name}
        onChange={(event) => setForm({ ...form, from_name: event.target.value })}
        error={errors.from_name}
      />
      <div className="flex flex-wrap gap-3">
        <Button type="submit" isLoading={save.isPending} loadingLabel={t('settings.mail.saving')}>
          {t('settings.mail.save')}
        </Button>
        {isPanel && (
          <Button
            type="button"
            variant="secondary"
            isLoading={test.isPending}
            loadingLabel={t('settings.mail.testing')}
            onClick={() => test.mutate()}
          >
            {t('settings.mail.test')}
          </Button>
        )}
      </div>
      {isPanel && <p className="text-xs text-neutral-500">{t('settings.mail.testHint')}</p>}
      {test.isError && <Alert tone="error">{deliveryMessage(test.error)}</Alert>}
      {isPanel && !isConfirmingReset && (
        <div>
          <Button type="button" variant="ghost" onClick={() => setIsConfirmingReset(true)}>
            {t('settings.mail.reset')}
          </Button>
        </div>
      )}
      {isConfirmingReset && (
        <div className="flex flex-col gap-3 rounded-md border border-border px-3 py-3">
          <p className="text-sm text-neutral-800">{t('settings.mail.resetQuestion')}</p>
          {reset.isError && <Alert tone="error">{generalErrorMessage(reset.error) ?? t('common.genericError')}</Alert>}
          <div className="flex flex-wrap gap-3">
            <Button
              type="button"
              variant="danger"
              isLoading={reset.isPending}
              loadingLabel={t('settings.mail.resetting')}
              onClick={() => reset.mutate()}
            >
              {t('settings.mail.resetConfirm')}
            </Button>
            <Button type="button" variant="secondary" onClick={() => setIsConfirmingReset(false)}>
              {t('settings.mail.cancel')}
            </Button>
          </div>
        </div>
      )}
    </form>
  )
}

type MailConfigFieldProps = {
  field: MailField
  value: string
  isSecretSet: boolean
  isClearing: boolean
  error?: string
  onChange: (value: string) => void
  onClearingChange: (isClearing: boolean) => void
}

function MailConfigField({ field, value, isSecretSet, isClearing, error, onChange, onClearingChange }: MailConfigFieldProps) {
  const label = t(field.label)
  const optionalHint = field.isOptional ? t('settings.mail.optional') : undefined

  if (field.kind === 'select') {
    return (
      <SelectField label={label} name={field.key} value={value} onChange={(event) => onChange(event.target.value)} error={error}>
        {field.options?.map((option) => (
          <option key={option.value} value={option.value}>
            {t(option.label)}
          </option>
        ))}
      </SelectField>
    )
  }

  if (field.kind === 'secret') {
    const canClear = isSecretSet && field.isOptional === true && value === ''

    return (
      <div className="flex flex-col gap-1.5">
        <PasswordField
          label={label}
          name={field.key}
          autoComplete="off"
          value={value}
          hint={isSecretSet && !isClearing ? t('settings.mail.secretKeep') : optionalHint}
          onChange={(event) => onChange(event.target.value)}
          error={error}
        />
        {canClear && !isClearing && (
          <Button variant="ghost" className="self-start" onClick={() => onClearingChange(true)}>
            {t('settings.mail.removeSecret')}
          </Button>
        )}
        {canClear && isClearing && (
          <p className="text-xs text-neutral-500">
            {t('settings.mail.secretWillBeRemoved')}{' '}
            <button type="button" className="font-semibold text-primary-700 underline" onClick={() => onClearingChange(false)}>
              {t('settings.mail.keepSecret')}
            </button>
          </p>
        )}
      </div>
    )
  }

  return (
    <TextField
      label={label}
      name={field.key}
      type={field.kind === 'number' ? 'number' : 'text'}
      inputMode={field.kind === 'number' ? 'numeric' : undefined}
      autoComplete="off"
      value={value}
      hint={optionalHint}
      onChange={(event) => onChange(event.target.value)}
      error={error}
    />
  )
}
