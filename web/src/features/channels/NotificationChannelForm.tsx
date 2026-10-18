import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useRef, useState, type FormEvent } from 'react'
import { fieldErrors, generalErrorMessage } from '../../api/errors.ts'
import { createNotificationChannel, updateNotificationChannel } from '../../api/notificationChannels.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import { listTeams } from '../../api/teams.ts'
import type { NotificationChannel, NotificationChannelInput, NotificationChannelType } from '../../api/types.ts'
import { Alert } from '../../components/ui/Alert.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { SelectField } from '../../components/ui/SelectField.tsx'
import { TextField } from '../../components/ui/TextField.tsx'
import { useToast } from '../../components/ui/toast.ts'
import { t } from '../../i18n/index.ts'
import { TeamPicker } from '../teams/TeamPicker.tsx'
import { initialTeamIds, teamIdsError } from '../teams/teamSelection.ts'
import { channelTypeOrder, channelTypes } from './channelTypes.ts'

type HeaderRow = { id: number; name: string; value: string }

type NotificationChannelFormProps = {
  channel?: NotificationChannel
  onSaved: (channel: NotificationChannel) => void
  onCancel: () => void
  cancelLabel?: string
  submitLabel?: string
  submittingLabel?: string
}

export function NotificationChannelForm({
  channel,
  onSaved,
  onCancel,
  cancelLabel = t('common.cancel'),
  submitLabel,
  submittingLabel,
}: NotificationChannelFormProps) {
  const queryClient = useQueryClient()
  const { showToast } = useToast()
  const [type, setType] = useState<NotificationChannelType>(channel?.type ?? 'telegram')
  const [name, setName] = useState(channel?.name ?? '')
  const [config, setConfig] = useState<Record<string, string>>(channel?.config ?? {})
  const [enabled, setEnabled] = useState(channel?.enabled ?? true)
  const [isDefault, setIsDefault] = useState(channel?.is_default ?? true)
  const hasSavedHeaders = channel?.secrets_set.includes('headers') ?? false
  const [isReplacingHeaders, setIsReplacingHeaders] = useState(!hasSavedHeaders)
  const [headers, setHeaders] = useState<HeaderRow[]>([])
  const nextHeaderId = useRef(0)
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
  const isSecretSaved = (key: string) => channel?.secrets_set.includes(key) ?? false

  const changeType = (next: NotificationChannelType) => {
    setType(next)
    setConfig({})
    setHeaders([])
  }

  const updateHeader = (id: number, row: Partial<HeaderRow>) =>
    setHeaders(headers.map((header) => (header.id === id ? { ...header, ...row } : header)))

  const addHeader = () => setHeaders([...headers, { id: nextHeaderId.current++, name: '', value: '' }])

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
      payload.headers = Object.fromEntries(
        headers.filter((header) => header.name.trim() !== '').map((header) => [header.name.trim(), header.value]),
      )
    }

    mutation.mutate({
      ...(channel ? {} : { type }),
      name: name.trim(),
      enabled,
      is_default: isDefault,
      config: payload,
      ...(channel && selectedTeamIds === null ? {} : { team_ids: teamIds }),
    })
  }

  return (
    <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
      {generalError && <Alert tone="error">{generalError}</Alert>}
      {!channel && (
        <SelectField
          id="channel-type"
          label={t('settings.channels.form.type')}
          name="type"
          value={type}
          onChange={(event) => changeType(channelTypeOrder.find((option) => option === event.target.value) ?? type)}
          error={errors.type}
        >
          {channelTypeOrder.map((option) => (
            <option key={option} value={option}>
              {t(`settings.channels.types.${option}`)}
            </option>
          ))}
        </SelectField>
      )}

      <details className="rounded-md border border-border bg-neutral-50 px-4 py-3 text-sm">
        <summary className="cursor-pointer font-medium text-neutral-800">{t('settings.channels.form.howTo')}</summary>
        <ol className="mt-3 flex list-decimal flex-col gap-2 pl-5 text-neutral-700">
          {definition.help.map((step) => (
            <li key={step}>{t(step)}</li>
          ))}
        </ol>
      </details>

      <TextField
        label={t('settings.channels.form.name')}
        name="name"
        required
        maxLength={100}
        placeholder={t('settings.channels.form.namePlaceholder')}
        value={name}
        onChange={(event) => setName(event.target.value)}
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
          onChange={(event) => setConfig({ ...config, [field.key]: event.target.value })}
          error={errors[`config.${field.key}`] ?? errors.config}
        />
      ))}

      {definition.hasHeaders && (
        <fieldset className="flex flex-col gap-2">
          <legend className="text-sm font-medium text-neutral-800">{t('settings.channels.headers.label')}</legend>
          <p className="text-xs text-neutral-500">{t('settings.channels.headers.hint')}</p>
          {!isReplacingHeaders ? (
            <div className="flex flex-wrap items-center gap-2 text-sm text-neutral-700">
              <span>{t('settings.channels.headers.saved')}</span>
              <Button variant="secondary" onClick={() => setIsReplacingHeaders(true)}>
                {t('settings.channels.headers.replace')}
              </Button>
            </div>
          ) : (
            <>
              {headers.map((header, index) => (
                <div key={header.id} className="flex items-end gap-2">
                  <TextField
                    className="flex-1"
                    label={t('settings.channels.headers.name')}
                    autoComplete="off"
                    placeholder="Authorization"
                    value={header.name}
                    onChange={(event) => updateHeader(header.id, { name: event.target.value })}
                  />
                  <TextField
                    className="flex-1"
                    label={t('settings.channels.headers.value')}
                    type="password"
                    autoComplete="off"
                    value={header.value}
                    onChange={(event) => updateHeader(header.id, { value: event.target.value })}
                  />
                  <Button
                    variant="ghost"
                    aria-label={t('settings.channels.headers.remove', { position: index + 1 })}
                    onClick={() => setHeaders(headers.filter((current) => current.id !== header.id))}
                  >
                    ×
                  </Button>
                </div>
              ))}
              <div>
                <Button variant="secondary" onClick={addHeader}>
                  {t('settings.channels.headers.add')}
                </Button>
              </div>
            </>
          )}
          {headerError && <p className="text-xs font-medium text-status-down">{headerError}</p>}
        </fieldset>
      )}

      <TeamPicker teams={teams.data} selectedIds={teamIds} onChange={setSelectedTeamIds} error={teamIdsError(errors)} />

      <div className="flex flex-col gap-2">
        <label className="inline-flex items-center gap-2 text-sm font-medium text-neutral-800">
          <input type="checkbox" checked={enabled} onChange={(event) => setEnabled(event.target.checked)} className="size-4 accent-primary-600" />
          {t('settings.channels.form.enabled')}
        </label>
        <label className="inline-flex items-center gap-2 text-sm font-medium text-neutral-800">
          <input type="checkbox" checked={isDefault} onChange={(event) => setIsDefault(event.target.checked)} className="size-4 accent-primary-600" />
          {t('settings.channels.form.isDefault')}
        </label>
      </div>

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
    </form>
  )
}
