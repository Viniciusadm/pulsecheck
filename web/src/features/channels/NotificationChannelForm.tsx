import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent, type ReactNode } from 'react'
import { fieldErrors, generalErrorMessage } from '../../api/errors.ts'
import { createNotificationChannel, updateNotificationChannel } from '../../api/notificationChannels.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import { listTeams } from '../../api/teams.ts'
import type { MessageFormat, NotificationChannel, NotificationChannelInput, NotificationChannelType, NotificationEvent } from '../../api/types.ts'
import { Alert } from '../../components/ui/Alert.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { FormSection } from '../../components/ui/FormSection.tsx'
import { HeaderRowsEditor } from '../../components/ui/HeaderRowsEditor.tsx'
import { headerRowsToRecord, type HeaderRow } from '../../components/ui/headerRows.ts'
import { SelectField } from '../../components/ui/SelectField.tsx'
import { TextField } from '../../components/ui/TextField.tsx'
import { useToast } from '../../components/ui/toast.ts'
import { t } from '../../i18n/index.ts'
import { TeamPicker } from '../teams/TeamPicker.tsx'
import { initialTeamIds, teamIdsError } from '../teams/teamSelection.ts'
import { ChannelPreview } from './ChannelPreview.tsx'
import { channelTypeOrder, channelTypes, notificationEventOrder, type ChannelOption } from './channelTypes.ts'

type NotificationChannelFormProps = {
  channel?: NotificationChannel
  layout?: 'compact' | 'page'
  onSaved: (channel: NotificationChannel) => void
  onCancel: () => void
  cancelLabel?: string
  submitLabel?: string
  submittingLabel?: string
}

const DEFAULT_QUIET_START = '22:00'
const DEFAULT_QUIET_END = '07:00'

function textConfig(config: Record<string, string | boolean> | undefined): Record<string, string> {
  return Object.fromEntries(Object.entries(config ?? {}).filter((entry): entry is [string, string] => typeof entry[1] === 'string'))
}

function initialOptions(type: NotificationChannelType, config: Record<string, string | boolean> | undefined): Record<string, string | boolean> {
  return Object.fromEntries(
    (channelTypes[type].options ?? []).map((option) => [option.key, config?.[option.key] ?? (option.kind === 'checkbox' ? false : '')]),
  )
}

export function NotificationChannelForm({
  channel,
  layout = 'compact',
  onSaved,
  onCancel,
  cancelLabel = t('common.cancel'),
  submitLabel,
  submittingLabel,
}: NotificationChannelFormProps) {
  const queryClient = useQueryClient()
  const { showToast } = useToast()
  const isPage = layout === 'page'
  const [type, setType] = useState<NotificationChannelType>(channel?.type ?? 'telegram')
  const [name, setName] = useState(channel?.name ?? '')
  const [config, setConfig] = useState<Record<string, string>>(() => textConfig(channel?.config))
  const [options, setOptions] = useState(() => initialOptions(channel?.type ?? 'telegram', channel?.config))
  const [enabled, setEnabled] = useState(channel?.enabled ?? true)
  const [isDefault, setIsDefault] = useState(channel?.is_default ?? true)
  const [events, setEvents] = useState<NotificationEvent[]>(channel?.events ?? notificationEventOrder)
  const [hasQuietHours, setHasQuietHours] = useState(channel?.quiet_hours_start != null)
  const [quietStart, setQuietStart] = useState(channel?.quiet_hours_start ?? DEFAULT_QUIET_START)
  const [quietEnd, setQuietEnd] = useState(channel?.quiet_hours_end ?? DEFAULT_QUIET_END)
  const [allowCritical, setAllowCritical] = useState(channel?.quiet_hours_allow_critical ?? true)
  const [messageFormat, setMessageFormat] = useState<MessageFormat>(channel?.message_format ?? 'detailed')
  const hasSavedHeaders = channel?.secrets_set.includes('headers') ?? false
  const [isReplacingHeaders, setIsReplacingHeaders] = useState(!hasSavedHeaders)
  const [headers, setHeaders] = useState<HeaderRow[]>([])
  const teams = useQuery({ queryKey: queryKeys.teams, queryFn: listTeams })
  const [selectedTeamIds, setSelectedTeamIds] = useState<number[] | null>(null)
  const teamIds = selectedTeamIds ?? initialTeamIds(teams.data, channel?.team_ids)

  const mutation = useMutation({
    mutationFn: (input: NotificationChannelInput) =>
      channel ? updateNotificationChannel(channel.id, input) : createNotificationChannel(input),
    onSuccess: (saved) => {
      void queryClient.invalidateQueries({ queryKey: queryKeys.notificationChannels })
      void queryClient.invalidateQueries({ queryKey: queryKeys.teams })
      showToast(channel ? t('settings.channels.form.saved') : t('settings.channels.form.created'))
      onSaved(saved)
    },
  })

  const definition = channelTypes[type]
  const errors = fieldErrors(mutation.error)
  const generalError = mutation.isError ? generalErrorMessage(mutation.error) : null
  const headerError = Object.entries(errors).find(([field]) => field.startsWith('config.headers'))?.[1]
  const eventsError = Object.entries(errors).find(([field]) => field.startsWith('events'))?.[1]
  const isSecretSaved = (key: string) => channel?.secrets_set.includes(key) ?? false

  const changeType = (next: NotificationChannelType) => {
    setType(next)
    setConfig({})
    setOptions(initialOptions(next, undefined))
    setHeaders([])
  }

  const toggleEvent = (event: NotificationEvent, isChecked: boolean) =>
    setEvents(notificationEventOrder.filter((option) => (option === event ? isChecked : events.includes(option))))

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault()

    if (mutation.isPending) {
      return
    }

    const payload: NotificationChannelInput['config'] = {}

    for (const field of definition.fields) {
      const value = (config[field.key] ?? '').trim()

      if (value !== '' || !(field.isSecret && isSecretSaved(field.key))) {
        payload[field.key] = value
      }
    }

    if (definition.hasHeaders && isReplacingHeaders) {
      payload.headers = headerRowsToRecord(headers)
    }

    if (isPage) {
      for (const option of definition.options ?? []) {
        const value = options[option.key]

        if (typeof value === 'boolean') {
          payload[option.key] = value
        } else if ((value ?? '').trim() !== '') {
          payload[option.key] = (value ?? '').trim()
        } else if (channel) {
          payload[option.key] = null
        }
      }
    }

    mutation.mutate({
      ...(channel ? {} : { type }),
      name: name.trim(),
      enabled,
      is_default: isDefault,
      ...(isPage
        ? {
            events,
            quiet_hours_start: hasQuietHours ? quietStart : null,
            quiet_hours_end: hasQuietHours ? quietEnd : null,
            quiet_hours_allow_critical: allowCritical,
            message_format: messageFormat,
          }
        : {}),
      config: payload,
      ...(channel && selectedTeamIds === null ? {} : { team_ids: teamIds }),
    })
  }

  const helpSteps = (
    <ol className="flex list-decimal flex-col gap-2 pl-5 text-neutral-700">
      {definition.help.map((step) => (
        <li key={step}>{t(step)}</li>
      ))}
    </ol>
  )

  const connectionFields = (
    <>
      {!channel && (
        <SelectField
          id="channel-type"
          label={t('settings.channels.form.type')}
          name="type"
          value={type}
          onChange={(change) => changeType(channelTypeOrder.find((option) => option === change.target.value) ?? type)}
          error={errors.type}
        >
          {channelTypeOrder.map((option) => (
            <option key={option} value={option}>
              {t(`settings.channels.types.${option}`)}
            </option>
          ))}
        </SelectField>
      )}

      {isPage ? (
        <div className="flex flex-col gap-2 rounded-md border border-border bg-neutral-50 px-4 py-3 text-sm">
          <p className="font-medium text-neutral-800">{t('settings.channels.form.howTo')}</p>
          {helpSteps}
        </div>
      ) : (
        <details className="rounded-md border border-border bg-neutral-50 px-4 py-3 text-sm">
          <summary className="cursor-pointer font-medium text-neutral-800">{t('settings.channels.form.howTo')}</summary>
          <div className="mt-3">{helpSteps}</div>
        </details>
      )}

      <TextField
        label={t('settings.channels.form.name')}
        name="name"
        required
        maxLength={100}
        placeholder={t('settings.channels.form.namePlaceholder')}
        value={name}
        onChange={(change) => setName(change.target.value)}
        error={errors.name}
      />

      {definition.fields.map((field) => (
        <TextField
          key={`${type}-${field.key}`}
          label={t(field.label)}
          name={field.key}
          type={field.inputType}
          autoComplete="off"
          placeholder={field.isSecret && isSecretSaved(field.key) ? t('settings.channels.form.secretSaved') : field.placeholder}
          hint={field.hint ? t(field.hint) : undefined}
          value={config[field.key] ?? ''}
          onChange={(change) => setConfig({ ...config, [field.key]: change.target.value })}
          error={errors[`config.${field.key}`] ?? errors.config}
        />
      ))}

      {definition.hasHeaders && (
        <HeaderRowsEditor
          rows={headers}
          onChange={setHeaders}
          hint={t('settings.channels.headers.hint')}
          isReplacing={isReplacingHeaders}
          onReplace={() => setIsReplacingHeaders(true)}
          error={headerError}
        />
      )}

      <TeamPicker teams={teams.data} selectedIds={teamIds} onChange={setSelectedTeamIds} error={teamIdsError(errors)} />

      <div className="flex flex-col gap-2">
        <Checkbox label={t('settings.channels.form.enabled')} checked={enabled} onChange={setEnabled} />
        <Checkbox label={t('settings.channels.form.isDefault')} checked={isDefault} onChange={setIsDefault} />
      </div>
    </>
  )

  const actions = (
    <div className="mt-2 flex flex-wrap justify-end gap-2">
      <Button variant="secondary" onClick={onCancel} disabled={mutation.isPending}>
        {cancelLabel}
      </Button>
      <Button
        type="submit"
        isLoading={mutation.isPending}
        loadingLabel={submittingLabel ?? (channel ? t('settings.channels.form.saving') : t('settings.channels.form.creating'))}
      >
        {submitLabel ?? (channel ? t('settings.channels.form.save') : t('settings.channels.form.create'))}
      </Button>
    </div>
  )

  if (!isPage) {
    return (
      <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
        {generalError && <Alert tone="error">{generalError}</Alert>}
        {connectionFields}
        {actions}
      </form>
    )
  }

  return (
    <form onSubmit={handleSubmit} noValidate className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
      <div className="flex min-w-0 flex-col gap-6">
        {generalError && <Alert tone="error">{generalError}</Alert>}
        <FormSection title={t('settings.channels.page.sections.connection')}>{connectionFields}</FormSection>

        <FormSection title={t('settings.channels.page.sections.events')} description={t('settings.channels.page.eventsHint')}>
          <fieldset className="grid gap-2 sm:grid-cols-2" aria-label={t('settings.channels.page.sections.events')}>
            {notificationEventOrder.map((option) => (
              <Checkbox
                key={option}
                label={t(`settings.channels.events.${option}`)}
                checked={events.includes(option)}
                onChange={(isChecked) => toggleEvent(option, isChecked)}
              />
            ))}
          </fieldset>
          {eventsError && <FieldError>{eventsError}</FieldError>}
        </FormSection>

        <FormSection title={t('settings.channels.page.sections.quietHours')} description={t('settings.channels.page.quietHoursHint')}>
          <Checkbox label={t('settings.channels.page.quietHoursEnabled')} checked={hasQuietHours} onChange={setHasQuietHours} />
          {hasQuietHours && (
            <>
              <div className="grid gap-4 sm:grid-cols-2">
                <TextField
                  label={t('settings.channels.page.quietStart')}
                  name="quiet_hours_start"
                  type="time"
                  required
                  value={quietStart}
                  onChange={(change) => setQuietStart(change.target.value)}
                  error={errors.quiet_hours_start}
                />
                <TextField
                  label={t('settings.channels.page.quietEnd')}
                  name="quiet_hours_end"
                  type="time"
                  required
                  value={quietEnd}
                  onChange={(change) => setQuietEnd(change.target.value)}
                  error={errors.quiet_hours_end}
                />
              </div>
              <Checkbox label={t('settings.channels.page.allowCritical')} checked={allowCritical} onChange={setAllowCritical} />
            </>
          )}
        </FormSection>

        <FormSection title={t('settings.channels.page.sections.format')}>
          <fieldset className="flex flex-col gap-2">
            <legend className="mb-2 text-sm font-medium text-neutral-800">{t('settings.channels.page.formatLegend')}</legend>
            {(['detailed', 'compact'] as const).map((format) => (
              <label key={format} className="flex items-start gap-2 text-sm text-neutral-800">
                <input
                  type="radio"
                  name="message_format"
                  value={format}
                  checked={messageFormat === format}
                  onChange={() => setMessageFormat(format)}
                  className="mt-0.5 size-4 accent-primary-600"
                />
                <span className="flex flex-col">
                  <span className="font-medium">{t(format === 'detailed' ? 'settings.channels.page.formatDetailed' : 'settings.channels.page.formatCompact')}</span>
                  <span className="text-xs text-neutral-500">
                    {t(format === 'detailed' ? 'settings.channels.page.formatDetailedHint' : 'settings.channels.page.formatCompactHint')}
                  </span>
                </span>
              </label>
            ))}
          </fieldset>
          {(definition.options ?? []).map((option) => (
            <OptionField
              key={`${type}-${option.key}`}
              option={option}
              value={options[option.key]}
              onChange={(value) => setOptions({ ...options, [option.key]: value })}
              error={errors[`config.${option.key}`]}
            />
          ))}
        </FormSection>

        {actions}
      </div>

      <aside className="lg:sticky lg:top-6">
        <ChannelPreview key={type} channel={channel} type={type} events={events} messageFormat={messageFormat} options={options} />
      </aside>
    </form>
  )
}

function Checkbox({ label, checked, onChange }: { label: string; checked: boolean; onChange: (isChecked: boolean) => void }) {
  return (
    <label className="inline-flex items-center gap-2 text-sm font-medium text-neutral-800">
      <input type="checkbox" checked={checked} onChange={(change) => onChange(change.target.checked)} className="size-4 accent-primary-600" />
      {label}
    </label>
  )
}

function FieldError({ children }: { children: ReactNode }) {
  return <p className="text-xs font-medium text-status-down">{children}</p>
}

type OptionFieldProps = {
  option: ChannelOption
  value: string | boolean | undefined
  onChange: (value: string | boolean) => void
  error?: string
}

function OptionField({ option, value, onChange, error }: OptionFieldProps) {
  if (option.kind === 'checkbox') {
    return (
      <div className="flex flex-col gap-1">
        <Checkbox label={t(option.label)} checked={value === true} onChange={onChange} />
        <p className="pl-6 text-xs text-neutral-500">{t(option.hint)}</p>
        {error && <FieldError>{error}</FieldError>}
      </div>
    )
  }

  if (option.kind === 'select') {
    return (
      <SelectField
        label={t(option.label)}
        name={option.key}
        hint={t(option.hint)}
        value={typeof value === 'string' ? value : ''}
        onChange={(change) => onChange(change.target.value)}
        error={error}
      >
        {option.choices.map((choice) => (
          <option key={choice.value} value={choice.value}>
            {t(choice.label)}
          </option>
        ))}
      </SelectField>
    )
  }

  return (
    <TextField
      label={t(option.label)}
      name={option.key}
      inputMode="numeric"
      autoComplete="off"
      placeholder={option.placeholder}
      hint={t(option.hint)}
      value={typeof value === 'string' ? value : ''}
      onChange={(change) => onChange(change.target.value)}
      error={error}
    />
  )
}
