import { Link } from 'react-router'
import type { Incident } from '../../api/types.ts'
import { Badge } from '../../components/ui/Badge.tsx'
import { t } from '../../i18n/index.ts'
import { formatDateTime, formatDuration } from '../../lib/format.ts'

export function IncidentList({ incidents, showMonitor = false }: { incidents: Incident[]; showMonitor?: boolean }) {
  return (
    <ul className="divide-y divide-border rounded-lg border border-border bg-surface">
      {incidents.map((incident) => (
        <li key={incident.id} className="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:gap-4">
          <div className="min-w-0 flex-1">
            {showMonitor && incident.monitor && (
              <Link to={`/monitors/${incident.monitor.id}`} className="font-semibold text-neutral-900 hover:text-primary-700 hover:underline">
                {incident.monitor.name}
              </Link>
            )}
            <p className="truncate text-neutral-700" title={incident.cause}>
              {incident.cause}
            </p>
            <p className="text-xs text-neutral-500">
              <time dateTime={incident.started_at}>{formatDateTime(incident.started_at)}</time>
            </p>
          </div>
          <div className="flex items-center gap-3">
            {incident.kind === 'degradation' && (
              <Badge className="bg-status-degraded-soft text-status-degraded">{t('monitors.incidents.kinds.degradation')}</Badge>
            )}
            <span className="tabular-nums text-neutral-700">{formatDuration(incident.duration_seconds)}</span>
            {incident.state === 'open' ? (
              <Badge className="bg-status-down-soft text-status-down">{t('monitors.incidents.open')}</Badge>
            ) : (
              <Badge className="bg-neutral-100 text-neutral-600">{t('monitors.incidents.resolved')}</Badge>
            )}
          </div>
        </li>
      ))}
    </ul>
  )
}
