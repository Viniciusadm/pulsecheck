import { useQuery } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { getAppConfig } from '../../api/dashboard.ts'
import { fieldErrors, generalErrorMessage } from '../../api/errors.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import type { AppConfig, Monitor, MonitorInput } from '../../api/types.ts'
import { Alert } from '../../components/ui/Alert.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { Dialog } from '../../components/ui/Dialog.tsx'
import { Spinner } from '../../components/ui/Spinner.tsx'
import { TextField } from '../../components/ui/TextField.tsx'
import { useToast } from '../../components/ui/toast.ts'
import { t } from '../../i18n/index.ts'
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
    timeout_seconds: monitor?.timeout_seconds ?? config?.monitors.timeout_default ?? 10,
  })

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

    const input: MonitorInput = { ...form, name: form.name.trim(), url: form.url.trim() }
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
    <Dialog isOpen={isOpen} title={monitor ? t('monitors.form.editTitle') : t('monitors.form.createTitle')} onClose={onClose}>
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
          <div className="flex flex-col gap-1.5">
            <label htmlFor="monitor-interval" className="text-sm font-medium text-neutral-800">
              {t('monitors.form.interval')}
            </label>
            <select
              id="monitor-interval"
              name="interval_minutes"
              value={form.interval_minutes}
              onChange={(event) => setForm({ ...form, interval_minutes: Number(event.target.value) })}
              aria-invalid={errors.interval_minutes ? true : undefined}
              className="min-h-10 rounded-md border border-neutral-300 bg-surface px-3 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200"
            >
              {intervals.map((minutes) => (
                <option key={minutes} value={minutes}>
                  {t('monitors.form.intervalOption', { minutes })}
                </option>
              ))}
            </select>
            {errors.interval_minutes && <p className="text-xs font-medium text-status-down">{errors.interval_minutes}</p>}
          </div>
          <TextField
            label={t('monitors.form.timeout')}
            name="timeout_seconds"
            type="number"
            min={timeoutMin}
            max={timeoutMax}
            hint={t('monitors.form.timeoutHint', { min: timeoutMin, max: timeoutMax })}
            value={form.timeout_seconds}
            onChange={(event) => setForm({ ...form, timeout_seconds: Number(event.target.value) })}
            error={errors.timeout_seconds}
          />
        </div>
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
