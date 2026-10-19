import { useQuery } from '@tanstack/react-query'
import { useId, useState, type FormEvent } from 'react'
import { getAppConfig } from '../../api/config.ts'
import { fieldErrors, generalErrorMessage } from '../../api/errors.ts'
import { listAllMonitors } from '../../api/allMonitors.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import { listTeams } from '../../api/teams.ts'
import type { MaintenanceWindow, MaintenanceWindowInput, MaintenanceWindowType } from '../../api/types.ts'
import { Alert } from '../../components/ui/Alert.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { Dialog } from '../../components/ui/Dialog.tsx'
import { SelectField } from '../../components/ui/SelectField.tsx'
import { TextField } from '../../components/ui/TextField.tsx'
import { useToast } from '../../components/ui/toast.ts'
import { t } from '../../i18n/index.ts'
import { isoToZonedInput, timeZoneOptions, zonedInputToIso } from '../../lib/timeZones.ts'
import { TeamPicker } from '../teams/TeamPicker.tsx'
import { WEEKDAYS, weekdayLabel } from './maintenanceSummary.ts'
import { useSaveMaintenanceWindow } from './useMaintenanceWindowMutations.ts'

type MaintenanceWindowDialogProps = {
  window?: MaintenanceWindow
  initialMonitorIds?: number[]
  onClose: () => void
}

const WINDOW_TYPES: MaintenanceWindowType[] = ['one_off', 'recurring']

export function MaintenanceWindowDialog({ window, initialMonitorIds = [], onClose }: MaintenanceWindowDialogProps) {
  const { showToast } = useToast()
  const monitorsLegendId = useId()
  const { data: config } = useQuery({ queryKey: queryKeys.config, queryFn: getAppConfig, staleTime: Infinity })
  const instanceTimeZone = config?.instance.timezone ?? 'UTC'
  const monitors = useQuery({ queryKey: queryKeys.allMonitors, queryFn: listAllMonitors })
  const teams = useQuery({ queryKey: queryKeys.teams, queryFn: listTeams })
  const mutation = useSaveMaintenanceWindow(window?.id)

  const [form, setForm] = useState({
    name: window?.name ?? '',
    type: window?.type ?? ('one_off' as MaintenanceWindowType),
    startsAt: window?.starts_at ? isoToZonedInput(window.starts_at, instanceTimeZone) : '',
    endsAt: window?.ends_at ? isoToZonedInput(window.ends_at, instanceTimeZone) : '',
    weekdays: window?.weekdays ?? [],
    startTime: window?.start_time ?? '',
    endTime: window?.end_time ?? '',
    timezone: window?.timezone ?? instanceTimeZone,
    monitorIds: window?.monitor_ids ?? initialMonitorIds,
    teamIds: window?.team_ids ?? [],
  })

  const errors = fieldErrors(mutation.error)
  const generalError = mutation.isError ? generalErrorMessage(mutation.error) : null
  const crossesMidnight = form.startTime !== '' && form.endTime !== '' && form.endTime <= form.startTime

  const toggle = (values: number[], value: number, isChecked: boolean) => (isChecked ? [...values, value] : values.filter((current) => current !== value))

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault()

    if (mutation.isPending) {
      return
    }

    const input: MaintenanceWindowInput = {
      name: form.name.trim(),
      type: form.type,
      monitor_ids: form.monitorIds,
      team_ids: form.teamIds,
      ...(form.type === 'one_off'
        ? {
            starts_at: form.startsAt === '' ? '' : zonedInputToIso(form.startsAt, instanceTimeZone),
            ends_at: form.endsAt === '' ? '' : zonedInputToIso(form.endsAt, instanceTimeZone),
          }
        : { weekdays: [...form.weekdays].sort((first, second) => first - second), start_time: form.startTime, end_time: form.endTime, timezone: form.timezone }),
    }

    mutation.mutate(input, {
      onSuccess: () => {
        showToast(window ? t('maintenance.saved') : t('maintenance.created'))
        onClose()
      },
    })
  }

  return (
    <Dialog isOpen title={window ? t('maintenance.form.editTitle') : t('maintenance.form.createTitle')} onClose={onClose} isBusy={mutation.isPending}>
      <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
        {generalError && <Alert tone="error">{generalError}</Alert>}
        <TextField
          label={t('maintenance.form.name')}
          name="name"
          required
          maxLength={100}
          placeholder={t('maintenance.form.namePlaceholder')}
          value={form.name}
          onChange={(event) => setForm({ ...form, name: event.target.value })}
          error={errors.name}
        />
        <SelectField
          label={t('maintenance.form.type')}
          name="type"
          value={form.type}
          onChange={(event) => setForm({ ...form, type: WINDOW_TYPES.find((type) => type === event.target.value) ?? form.type })}
          error={errors.type}
        >
          {WINDOW_TYPES.map((type) => (
            <option key={type} value={type}>
              {t(`maintenance.form.types.${type}`)}
            </option>
          ))}
        </SelectField>

        {form.type === 'one_off' ? (
          <div className="grid gap-4 sm:grid-cols-2">
            <TextField
              label={t('maintenance.form.startsAt')}
              name="starts_at"
              type="datetime-local"
              required
              hint={t('maintenance.form.timesHint', { timezone: instanceTimeZone })}
              value={form.startsAt}
              onChange={(event) => setForm({ ...form, startsAt: event.target.value })}
              error={errors.starts_at}
            />
            <TextField
              label={t('maintenance.form.endsAt')}
              name="ends_at"
              type="datetime-local"
              required
              value={form.endsAt}
              onChange={(event) => setForm({ ...form, endsAt: event.target.value })}
              error={errors.ends_at}
            />
          </div>
        ) : (
          <>
            <fieldset className="flex flex-col gap-2">
              <legend className="text-sm font-medium text-neutral-800">{t('maintenance.form.weekdays')}</legend>
              <div className="flex flex-wrap gap-3">
                {WEEKDAYS.map((day) => (
                  <label key={day} className="inline-flex items-center gap-1.5 text-sm text-neutral-800">
                    <input
                      type="checkbox"
                      checked={form.weekdays.includes(day)}
                      onChange={(event) => setForm({ ...form, weekdays: toggle(form.weekdays, day, event.target.checked) })}
                      className="size-4 accent-primary-600"
                    />
                    {weekdayLabel(day)}
                  </label>
                ))}
              </div>
              {errors.weekdays && <p className="text-xs font-medium text-status-down">{errors.weekdays}</p>}
            </fieldset>
            <div className="grid gap-4 sm:grid-cols-3">
              <TextField
                label={t('maintenance.form.startTime')}
                name="start_time"
                type="time"
                required
                value={form.startTime}
                onChange={(event) => setForm({ ...form, startTime: event.target.value })}
                error={errors.start_time}
              />
              <TextField
                label={t('maintenance.form.endTime')}
                name="end_time"
                type="time"
                required
                hint={crossesMidnight ? t('maintenance.form.crossesMidnight') : undefined}
                value={form.endTime}
                onChange={(event) => setForm({ ...form, endTime: event.target.value })}
                error={errors.end_time}
              />
              <SelectField
                label={t('maintenance.form.timezone')}
                name="timezone"
                value={form.timezone}
                onChange={(event) => setForm({ ...form, timezone: event.target.value })}
                error={errors.timezone}
              >
                {timeZoneOptions(form.timezone).map((zone) => (
                  <option key={zone} value={zone}>
                    {zone}
                  </option>
                ))}
              </SelectField>
            </div>
          </>
        )}

        {monitors.data && monitors.data.length > 0 && (
          <fieldset className="flex flex-col gap-2" aria-describedby={errors.monitor_ids ? `${monitorsLegendId}-error` : undefined}>
            <legend className="text-sm font-medium text-neutral-800">{t('maintenance.form.monitors')}</legend>
            <div className="flex max-h-48 flex-col gap-1.5 overflow-y-auto">
              {monitors.data.map((monitor) => (
                <label key={monitor.id} className="inline-flex items-center gap-2 text-sm text-neutral-800">
                  <input
                    type="checkbox"
                    checked={form.monitorIds.includes(monitor.id)}
                    onChange={(event) => setForm({ ...form, monitorIds: toggle(form.monitorIds, monitor.id, event.target.checked) })}
                    className="size-4 accent-primary-600"
                  />
                  <span className="font-medium">{monitor.name}</span>
                  <span className="truncate text-neutral-500">{monitor.display_target}</span>
                </label>
              ))}
            </div>
            {errors.monitor_ids && (
              <p id={`${monitorsLegendId}-error`} className="text-xs font-medium text-status-down">
                {errors.monitor_ids}
              </p>
            )}
          </fieldset>
        )}
        <TeamPicker
          teams={teams.data}
          selectedIds={form.teamIds}
          onChange={(teamIds) => setForm({ ...form, teamIds })}
          hint={t('maintenance.form.teamsHint')}
          error={Object.entries(errors).find(([field]) => field.startsWith('team_ids'))?.[1]}
        />

        <div className="mt-2 flex flex-wrap justify-end gap-2">
          <Button variant="secondary" onClick={onClose} disabled={mutation.isPending}>
            {t('common.cancel')}
          </Button>
          <Button
            type="submit"
            isLoading={mutation.isPending}
            loadingLabel={window ? t('maintenance.form.saving') : t('maintenance.form.creating')}
          >
            {window ? t('maintenance.form.save') : t('maintenance.form.create')}
          </Button>
        </div>
      </form>
    </Dialog>
  )
}
