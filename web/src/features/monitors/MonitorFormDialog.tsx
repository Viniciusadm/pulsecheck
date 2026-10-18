import { useQuery } from '@tanstack/react-query'
import { useId, useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { getAppConfig } from '../../api/config.ts'
import { fieldErrors, generalErrorMessage } from '../../api/errors.ts'
import { listNotificationChannels } from '../../api/notificationChannels.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import { listTeams } from '../../api/teams.ts'
import type { AppConfig, Monitor, MonitorInput, NotificationChannel } from '../../api/types.ts'
import { Alert } from '../../components/ui/Alert.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { Dialog } from '../../components/ui/Dialog.tsx'
import { SelectField } from '../../components/ui/SelectField.tsx'
import { Spinner } from '../../components/ui/Spinner.tsx'
import { TextField } from '../../components/ui/TextField.tsx'
import { useToast } from '../../components/ui/toast.ts'
import { t } from '../../i18n/index.ts'
import { TeamPicker } from '../teams/TeamPicker.tsx'
import { HttpRequestFields } from './HttpRequestFields.tsx'
import { changedRequestOptions, initialHttpRequestState, requestSecrets } from './httpRequest.ts'
import { initialTeamIds, teamIdsError } from '../teams/teamSelection.ts'
import { useCreateMonitor, useUpdateMonitor } from './useMonitorMutations.ts'

type MonitorFormDialogProps = {
  isOpen: boolean
  onClose: () => void
  onSaved?: (monitor: Monitor) => void
  monitor?: Monitor
}

export function MonitorFormDialog(props: MonitorFormDialogProps) {
  const { data: config, isError } = useQuery({ queryKey: queryKeys.config, queryFn: getAppConfig, staleTime: Infinity, enabled: props.isOpen })

  if (!props.isOpen) {
    return null
  }

  if (!config && !isError) {
    return (
      <Dialog isOpen title={props.monitor ? t('monitors.form.editTitle') : t('monitors.form.createTitle')} onClose={props.onClose}>
        <Spinner className="text-primary-600" />
      </Dialog>
    )
  }

  return <MonitorForm {...props} config={config} />
}

function MonitorForm({ isOpen, onClose, onSaved, monitor, config }: MonitorFormDialogProps & { config?: AppConfig }) {
  const { showToast } = useToast()
  const createMutation = useCreateMonitor()
  const updateMutation = useUpdateMonitor(monitor?.id ?? 0)
  const mutation = monitor ? updateMutation : createMutation

  const [form, setForm] = useState({
    name: monitor?.name ?? '',
    url: monitor?.url ?? '',
    interval_minutes: monitor?.interval_minutes ?? config?.monitors.default_interval ?? 5,
    timeout_seconds: String(monitor?.timeout_seconds ?? config?.monitors.timeout_default ?? 10),
    latency_threshold_ms: monitor?.latency_threshold_ms == null ? '' : String(monitor.latency_threshold_ms),
  })
  const [request, setRequest] = useState(() => initialHttpRequestState(monitor))

  const channels = useQuery({ queryKey: queryKeys.notificationChannels, queryFn: listNotificationChannels })
  const [selectedChannelIds, setSelectedChannelIds] = useState<number[] | null>(null)
  const channelIds = selectedChannelIds
    ?? monitor?.notification_channel_ids
    ?? (channels.data ?? []).filter((channel) => channel.is_default).map((channel) => channel.id)

  const teams = useQuery({ queryKey: queryKeys.teams, queryFn: listTeams })
  const [selectedTeamIds, setSelectedTeamIds] = useState<number[] | null>(null)
  const teamIds = selectedTeamIds ?? initialTeamIds(teams.data, monitor?.team_ids)

  const toggleChannel = (id: number, isChecked: boolean) =>
    setSelectedChannelIds(isChecked ? [...channelIds, id] : channelIds.filter((current) => current !== id))

  const errors = fieldErrors(mutation.error)
  const generalError = mutation.isError ? (errors.monitors ?? generalErrorMessage(mutation.error)) : null
  const intervals = config?.monitors.allowed_intervals ?? [1, 5, 10]
  const timeoutMin = config?.monitors.timeout_min ?? 1
  const timeoutMax = config?.monitors.timeout_max ?? 30

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault()

    if (mutation.isPending) {
      return
    }

    const input: MonitorInput = {
      name: form.name.trim(),
      url: form.url.trim(),
      interval_minutes: form.interval_minutes,
      ...(form.timeout_seconds.trim() === '' ? {} : { timeout_seconds: Number(form.timeout_seconds) }),
    }

    const latencyThresholdMs = form.latency_threshold_ms.trim() === '' ? null : Number(form.latency_threshold_ms)

    if (latencyThresholdMs !== (monitor?.latency_threshold_ms ?? null)) {
      input.latency_threshold_ms = latencyThresholdMs
    }

    const options = changedRequestOptions(request, monitor)
    const secrets = requestSecrets(request, monitor)

    if (options) {
      input.options = options
    }

    if (secrets) {
      input.secrets = secrets
    }

    if (selectedChannelIds !== null) {
      input.notification_channel_ids = selectedChannelIds
    }

    if (!monitor || selectedTeamIds !== null) {
      input.team_ids = teamIds
    }

    const onSuccess = (saved: Monitor) => {
      showToast(monitor ? t('monitors.form.saved') : t('monitors.form.created'))
      onSaved?.(saved)
      onClose()
    }

    if (monitor) {
      updateMutation.mutate(input, { onSuccess })
    } else {
      createMutation.mutate(input, { onSuccess })
    }
  }

  return (
    <Dialog isOpen={isOpen} title={monitor ? t('monitors.form.editTitle') : t('monitors.form.createTitle')} onClose={onClose} isBusy={mutation.isPending}>
      <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
        {generalError && <Alert tone="error">{generalError}</Alert>}
        <TextField
          label={t('monitors.form.name')}
          name="name"
          required
          maxLength={100}
          placeholder={t('monitors.form.namePlaceholder')}
          value={form.name}
          onChange={(event) => setForm({ ...form, name: event.target.value })}
          error={errors.name}
        />
        <TextField
          label={t('monitors.form.url')}
          name="url"
          type="url"
          inputMode="url"
          required
          placeholder="https://example.com"
          hint={t('monitors.form.urlHint')}
          value={form.url}
          onChange={(event) => setForm({ ...form, url: event.target.value })}
          error={errors.url}
        />
        <div className="grid gap-4 sm:grid-cols-2">
          <SelectField
            id="monitor-interval"
            label={t('monitors.form.interval')}
            name="interval_minutes"
            value={form.interval_minutes}
            onChange={(event) => setForm({ ...form, interval_minutes: Number(event.target.value) })}
            error={errors.interval_minutes}
          >
            {intervals.map((minutes) => (
              <option key={minutes} value={minutes}>
                {t('monitors.form.intervalOption', { minutes })}
              </option>
            ))}
          </SelectField>
          <TextField
            label={t('monitors.form.timeout')}
            name="timeout_seconds"
            type="number"
            min={timeoutMin}
            max={timeoutMax}
            hint={t('monitors.form.timeoutHint', { min: timeoutMin, max: timeoutMax })}
            value={form.timeout_seconds}
            onChange={(event) => setForm({ ...form, timeout_seconds: event.target.value })}
            error={errors.timeout_seconds}
          />
        </div>
        <TextField
          label={t('monitors.form.latencyThreshold')}
          name="latency_threshold_ms"
          type="number"
          min={1}
          inputMode="numeric"
          hint={t('monitors.form.latencyThresholdHint')}
          value={form.latency_threshold_ms}
          onChange={(event) => setForm({ ...form, latency_threshold_ms: event.target.value })}
          error={errors.latency_threshold_ms}
        />
        <HttpRequestFields state={request} onChange={setRequest} monitor={monitor} errors={errors} />
        <TeamPicker teams={teams.data} selectedIds={teamIds} onChange={setSelectedTeamIds} error={teamIdsError(errors)} />
        <ChannelPicker
          channels={channels.data}
          selectedIds={channelIds}
          onToggle={toggleChannel}
          error={Object.entries(errors).find(([field]) => field.startsWith('notification_channel_ids'))?.[1]}
        />
        <div className="mt-2 flex flex-wrap justify-end gap-2">
          <Button variant="secondary" onClick={onClose} disabled={mutation.isPending}>
            {t('common.cancel')}
          </Button>
          <Button
            type="submit"
            isLoading={mutation.isPending}
            loadingLabel={monitor ? t('monitors.form.saving') : t('monitors.form.creating')}
          >
            {monitor ? t('monitors.form.save') : t('monitors.form.create')}
          </Button>
        </div>
      </form>
    </Dialog>
  )
}

type ChannelPickerProps = {
  channels?: NotificationChannel[]
  selectedIds: number[]
  onToggle: (id: number, isChecked: boolean) => void
  error?: string
}

function ChannelPicker({ channels, selectedIds, onToggle, error }: ChannelPickerProps) {
  const hintId = useId()
  const errorId = useId()

  if (!channels) {
    return null
  }

  return (
    <fieldset className="flex flex-col gap-2" aria-describedby={error ? `${hintId} ${errorId}` : hintId}>
      <legend className="text-sm font-medium text-neutral-800">{t('monitors.form.notifications')}</legend>
      <p id={hintId} className="text-xs text-neutral-500">
        {t('monitors.form.notificationsHint')}
      </p>
      {channels.length === 0 ? (
        <p className="text-sm text-neutral-600">
          {t('monitors.form.noChannels')}{' '}
          <Link to="/channels" className="font-medium text-primary-700 hover:underline">
            {t('monitors.form.manageChannels')}
          </Link>
        </p>
      ) : (
        channels.map((channel) => (
          <label key={channel.id} className="inline-flex items-center gap-2 text-sm text-neutral-800">
            <input
              type="checkbox"
              name="notification_channel_ids"
              value={channel.id}
              checked={selectedIds.includes(channel.id)}
              onChange={(event) => onToggle(channel.id, event.target.checked)}
              aria-invalid={error ? true : undefined}
              className="size-4 accent-primary-600"
            />
            <span className="font-medium">{channel.name}</span>
            <span className="text-neutral-500">
              {t(`settings.channels.types.${channel.type}`)}
              {!channel.enabled && ` ${t('monitors.form.channelDisabled')}`}
            </span>
          </label>
        ))
      )}
      {error && (
        <p id={errorId} className="text-xs font-medium text-status-down">
          {error}
        </p>
      )}
    </fieldset>
  )
}
