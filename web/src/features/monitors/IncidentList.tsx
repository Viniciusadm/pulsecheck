import { useState } from 'react'
import { Link } from 'react-router'
import { generalErrorMessage } from '../../api/errors.ts'
import type { Incident } from '../../api/types.ts'
import { Badge } from '../../components/ui/Badge.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { RelativeTime } from '../../components/ui/RelativeTime.tsx'
import { useToast } from '../../components/ui/toast.ts'
import { t } from '../../i18n/index.ts'
import { formatDateTime, formatDuration } from '../../lib/format.ts'
import { IncidentNotes } from './IncidentNotes.tsx'
import { useAcknowledgeIncident } from './useIncidentMutations.ts'

export function IncidentList({ incidents, showMonitor = false }: { incidents: Incident[]; showMonitor?: boolean }) {
  return (
    <ul className="divide-y divide-border rounded-lg border border-border bg-surface">
      {incidents.map((incident) => (
        <IncidentItem key={incident.id} incident={incident} showMonitor={showMonitor} />
      ))}
    </ul>
  )
}

function IncidentItem({ incident, showMonitor }: { incident: Incident; showMonitor: boolean }) {
  const { showToast } = useToast()
  const acknowledgeMutation = useAcknowledgeIncident()
  const [isShowingNotes, setIsShowingNotes] = useState(false)
  const canAcknowledge = incident.state === 'open' && incident.acknowledged_at === null

  const acknowledge = () =>
    acknowledgeMutation.mutate(incident.id, {
      onSuccess: () => showToast(t('monitors.incidents.acknowledged')),
      onError: (error) => showToast(generalErrorMessage(error) ?? t('common.genericError'), 'error'),
    })

  return (
    <li className="flex flex-col gap-2 px-4 py-3">
      <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-4">
        <div className="min-w-0 flex-1">
          {showMonitor && incident.monitor && (
            <Link to={`/monitors/${incident.monitor.id}`} className="font-semibold text-neutral-900 hover:text-primary-700 hover:underline">
              {incident.monitor.name}
            </Link>
          )}
          <p className="truncate text-neutral-700" title={incident.cause}>
            {incident.cause}
          </p>
          <p className="flex flex-wrap gap-x-3 text-xs text-neutral-500">
            <time dateTime={incident.started_at}>{formatDateTime(incident.started_at)}</time>
            {incident.acknowledged_at && (
              <RelativeTime
                value={incident.acknowledged_at}
                format={(time) =>
                  incident.acknowledged_by
                    ? t('monitors.incidents.acknowledgedBy', { name: incident.acknowledged_by.name, time })
                    : t('monitors.incidents.acknowledgedAnonymous', { time })
                }
              />
            )}
            {incident.reminder_count > 0 && <span>{t('monitors.incidents.reminders', { count: incident.reminder_count })}</span>}
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-3">
          {incident.kind === 'degradation' && (
            <Badge className="bg-status-degraded-soft text-status-degraded">{t('monitors.incidents.kinds.degradation')}</Badge>
          )}
          <span className="tabular-nums text-neutral-700">{formatDuration(incident.duration_seconds)}</span>
          {incident.state === 'open' ? (
            <Badge className="bg-status-down-soft text-status-down">{t('monitors.incidents.open')}</Badge>
          ) : (
            <Badge className="bg-neutral-100 text-neutral-600">{t('monitors.incidents.resolved')}</Badge>
          )}
          {canAcknowledge && (
            <Button variant="secondary" onClick={acknowledge} isLoading={acknowledgeMutation.isPending} loadingLabel={t('monitors.incidents.acknowledging')}>
              {t('monitors.incidents.acknowledge')}
            </Button>
          )}
          <Button variant="ghost" aria-expanded={isShowingNotes} onClick={() => setIsShowingNotes(!isShowingNotes)}>
            {t('monitors.incidents.notes.toggle', { count: incident.notes_count ?? 0 })}
          </Button>
        </div>
      </div>
      {isShowingNotes && <IncidentNotes incidentId={incident.id} />}
    </li>
  )
}
