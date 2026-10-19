import { useQuery } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { listAllMonitors } from '../../api/allMonitors.ts'
import { fieldErrors, generalErrorMessage } from '../../api/errors.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import { listTeams } from '../../api/teams.ts'
import type { StatusPage, StatusPageInput } from '../../api/types.ts'
import { Alert } from '../../components/ui/Alert.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { Dialog } from '../../components/ui/Dialog.tsx'
import { SelectField } from '../../components/ui/SelectField.tsx'
import { TextField } from '../../components/ui/TextField.tsx'
import { useToast } from '../../components/ui/toast.ts'
import { t } from '../../i18n/index.ts'
import { TeamPicker } from '../teams/TeamPicker.tsx'
import { initialTeamIds, teamIdsError } from '../teams/teamSelection.ts'
import { useSaveStatusPage } from './useStatusPageMutations.ts'

type Entry = { monitorId: number; name: string; displayName: string }

const UPTIME_DAYS = [30, 60, 90]

function slugify(value: string): string {
  return value
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 60)
}

function move<T>(items: T[], from: number, to: number): T[] {
  const next = [...items]
  const [item] = next.splice(from, 1)
  next.splice(to, 0, item)

  return next
}

export function StatusPageDialog({ statusPage, onClose }: { statusPage?: StatusPage; onClose: () => void }) {
  const { showToast } = useToast()
  const mutation = useSaveStatusPage(statusPage?.id)
  const monitors = useQuery({ queryKey: queryKeys.allMonitors, queryFn: listAllMonitors })
  const teams = useQuery({ queryKey: queryKeys.teams, queryFn: listTeams })
  const [selectedTeamIds, setSelectedTeamIds] = useState<number[] | null>(null)
  const teamIds = selectedTeamIds ?? initialTeamIds(teams.data, statusPage?.team_ids)
  const [isSlugEdited, setIsSlugEdited] = useState(statusPage !== undefined)
  const [monitorToAdd, setMonitorToAdd] = useState('')
  const [form, setForm] = useState({
    name: statusPage?.name ?? '',
    slug: statusPage?.slug ?? '',
    description: statusPage?.description ?? '',
    isPublished: statusPage?.is_published ?? false,
    showLatency: statusPage?.show_latency ?? false,
    showIncidents: statusPage?.show_incidents ?? true,
    uptimeDays: statusPage?.uptime_days ?? 90,
  })
  const [entries, setEntries] = useState<Entry[]>(
    () => statusPage?.monitors.map((monitor) => ({ monitorId: monitor.monitor_id, name: monitor.name, displayName: monitor.display_name ?? monitor.name })) ?? [],
  )

  const errors = fieldErrors(mutation.error)
  const generalError = mutation.isError ? generalErrorMessage(mutation.error) : null
  const available = (monitors.data ?? []).filter((monitor) => !entries.some((entry) => entry.monitorId === monitor.id))

  const changeName = (name: string) => setForm({ ...form, name, slug: isSlugEdited ? form.slug : slugify(name) })

  const addMonitor = () => {
    const monitor = available.find((candidate) => String(candidate.id) === monitorToAdd)

    if (monitor) {
      setEntries([...entries, { monitorId: monitor.id, name: monitor.name, displayName: monitor.name }])
      setMonitorToAdd('')
    }
  }

  const updateEntry = (index: number, displayName: string) => setEntries(entries.map((entry, current) => (current === index ? { ...entry, displayName } : entry)))

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault()

    if (mutation.isPending) {
      return
    }

    const input: StatusPageInput = {
      name: form.name.trim(),
      slug: form.slug.trim(),
      description: form.description.trim() === '' ? null : form.description.trim(),
      is_published: form.isPublished,
      show_latency: form.showLatency,
      show_incidents: form.showIncidents,
      uptime_days: form.uptimeDays,
      monitors: entries.map((entry) => ({
        monitor_id: entry.monitorId,
        display_name: entry.displayName.trim() === '' || entry.displayName.trim() === entry.name ? null : entry.displayName.trim(),
      })),
      ...(statusPage && selectedTeamIds === null ? {} : { team_ids: teamIds }),
    }

    mutation.mutate(input, {
      onSuccess: () => {
        showToast(statusPage ? t('statusPages.saved') : t('statusPages.created'))
        onClose()
      },
    })
  }

  return (
    <Dialog isOpen title={statusPage ? t('statusPages.form.editTitle') : t('statusPages.form.createTitle')} onClose={onClose} isBusy={mutation.isPending}>
      <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
        {generalError && <Alert tone="error">{generalError}</Alert>}
        <TextField
          label={t('statusPages.form.name')}
          name="name"
          required
          maxLength={100}
          value={form.name}
          onChange={(event) => changeName(event.target.value)}
          error={errors.name}
        />
        <TextField
          label={t('statusPages.form.slug')}
          name="slug"
          required
          maxLength={60}
          hint={t('statusPages.form.slugHint', { slug: form.slug || '…' })}
          value={form.slug}
          onChange={(event) => {
            setIsSlugEdited(true)
            setForm({ ...form, slug: event.target.value })
          }}
          error={errors.slug}
        />
        <TextField
          label={t('statusPages.form.description')}
          name="description"
          maxLength={500}
          value={form.description}
          onChange={(event) => setForm({ ...form, description: event.target.value })}
          error={errors.description}
        />
        <div className="flex flex-col gap-2">
          <label className="inline-flex items-center gap-2 text-sm font-medium text-neutral-800">
            <input type="checkbox" checked={form.isPublished} onChange={(event) => setForm({ ...form, isPublished: event.target.checked })} className="size-4 accent-primary-600" />
            {t('statusPages.form.publish')}
          </label>
          {form.isPublished && <Alert tone="info">{t('statusPages.form.publishHint')}</Alert>}
          <label className="inline-flex items-center gap-2 text-sm font-medium text-neutral-800">
            <input type="checkbox" checked={form.showLatency} onChange={(event) => setForm({ ...form, showLatency: event.target.checked })} className="size-4 accent-primary-600" />
            {t('statusPages.form.showLatency')}
          </label>
          <label className="inline-flex items-center gap-2 text-sm font-medium text-neutral-800">
            <input type="checkbox" checked={form.showIncidents} onChange={(event) => setForm({ ...form, showIncidents: event.target.checked })} className="size-4 accent-primary-600" />
            {t('statusPages.form.showIncidents')}
          </label>
        </div>
        <SelectField
          label={t('statusPages.form.uptimeDays')}
          name="uptime_days"
          value={form.uptimeDays}
          onChange={(event) => setForm({ ...form, uptimeDays: Number(event.target.value) as StatusPage['uptime_days'] })}
          error={errors.uptime_days}
        >
          {UPTIME_DAYS.map((days) => (
            <option key={days} value={days}>
              {t('statusPages.form.uptimeDaysOption', { days })}
            </option>
          ))}
        </SelectField>

        <fieldset className="flex flex-col gap-2">
          <legend className="text-sm font-medium text-neutral-800">{t('statusPages.form.monitors')}</legend>
          {statusPage && statusPage.hidden_monitors_count > 0 && (
            <p className="text-xs text-neutral-500">{t('statusPages.hiddenMonitors', { count: statusPage.hidden_monitors_count })}</p>
          )}
          <ol className="flex flex-col gap-2">
            {entries.map((entry, index) => (
              <li key={entry.monitorId} className="flex items-end gap-2">
                <TextField
                  className="flex-1"
                  label={t('statusPages.form.displayName', { name: entry.name })}
                  maxLength={100}
                  value={entry.displayName}
                  onChange={(event) => updateEntry(index, event.target.value)}
                  error={errors[`monitors.${index}.display_name`] ?? errors[`monitors.${index}.monitor_id`]}
                />
                <Button variant="ghost" aria-label={t('statusPages.form.moveUp', { name: entry.name })} disabled={index === 0} onClick={() => setEntries(move(entries, index, index - 1))}>
                  ↑
                </Button>
                <Button
                  variant="ghost"
                  aria-label={t('statusPages.form.moveDown', { name: entry.name })}
                  disabled={index === entries.length - 1}
                  onClick={() => setEntries(move(entries, index, index + 1))}
                >
                  ↓
                </Button>
                <Button variant="ghost" aria-label={t('statusPages.form.remove', { name: entry.name })} onClick={() => setEntries(entries.filter((_, current) => current !== index))}>
                  ×
                </Button>
              </li>
            ))}
          </ol>
          {available.length > 0 ? (
            <div className="flex items-end gap-2">
              <SelectField className="flex-1" label={t('statusPages.form.addMonitor')} value={monitorToAdd} onChange={(event) => setMonitorToAdd(event.target.value)}>
                <option value="">—</option>
                {available.map((monitor) => (
                  <option key={monitor.id} value={monitor.id}>
                    {monitor.name}
                  </option>
                ))}
              </SelectField>
              <Button variant="secondary" onClick={addMonitor} disabled={monitorToAdd === ''}>
                {t('statusPages.form.addMonitor')}
              </Button>
            </div>
          ) : (
            monitors.data && <p className="text-xs text-neutral-500">{t('statusPages.form.noMonitorsLeft')}</p>
          )}
          {errors.monitors && <p className="text-xs font-medium text-status-down">{errors.monitors}</p>}
        </fieldset>

        <TeamPicker teams={teams.data} selectedIds={teamIds} onChange={setSelectedTeamIds} error={teamIdsError(errors)} />

        <div className="mt-2 flex flex-wrap justify-end gap-2">
          <Button variant="secondary" onClick={onClose} disabled={mutation.isPending}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" isLoading={mutation.isPending} loadingLabel={statusPage ? t('statusPages.form.saving') : t('statusPages.form.creating')}>
            {statusPage ? t('statusPages.form.save') : t('statusPages.form.create')}
          </Button>
        </div>
      </form>
    </Dialog>
  )
}
