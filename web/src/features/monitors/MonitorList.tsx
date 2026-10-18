import { Link } from 'react-router'
import type { Monitor } from '../../api/types.ts'
import { StatusBadge } from '../../components/ui/Badge.tsx'
import { t } from '../../i18n/index.ts'
import { formatDateTime, formatLatency, formatRelativeTime } from '../../lib/format.ts'
import { displayStatus, hostOf } from '../../lib/monitor.ts'

function LastCheck({ value }: { value: string | null }) {
  if (value === null) {
    return <span className="text-neutral-500">{t('monitors.neverChecked')}</span>
  }

  return (
    <time dateTime={value} title={formatDateTime(value)}>
      {formatRelativeTime(value)}
    </time>
  )
}

export function MonitorList({ monitors }: { monitors: Monitor[] }) {
  return (
    <>
      <div className="hidden overflow-hidden rounded-lg border border-border bg-surface md:block">
        <table className="w-full text-left">
          <thead className="border-b border-border bg-neutral-50 text-xs font-semibold text-neutral-600">
            <tr>
              <th scope="col" className="px-4 py-2.5">{t('monitors.columns.name')}</th>
              <th scope="col" className="px-4 py-2.5">{t('monitors.columns.status')}</th>
              <th scope="col" className="px-4 py-2.5 text-right">{t('monitors.columns.latency')}</th>
              <th scope="col" className="px-4 py-2.5">{t('monitors.columns.lastCheck')}</th>
              <th scope="col" className="px-4 py-2.5 text-right">{t('monitors.columns.httpCode')}</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {monitors.map((monitor) => (
              <tr key={monitor.id} className="hover:bg-neutral-50">
                <td className="px-4 py-3">
                  <Link to={`/monitors/${monitor.id}`} className="font-semibold text-neutral-900 hover:text-primary-700 hover:underline">
                    {monitor.name}
                  </Link>
                  <p className="text-xs text-neutral-500">{hostOf(monitor.url)}</p>
                </td>
                <td className="px-4 py-3">
                  <StatusBadge status={displayStatus(monitor)} />
                </td>
                <td className="px-4 py-3 text-right tabular-nums">{formatLatency(monitor.last_latency_ms)}</td>
                <td className="px-4 py-3 text-neutral-700">
                  <LastCheck value={monitor.last_checked_at} />
                </td>
                <td className="px-4 py-3 text-right tabular-nums text-neutral-700">{monitor.last_status_code ?? '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <ul className="flex flex-col gap-2 md:hidden">
        {monitors.map((monitor) => (
          <li key={monitor.id}>
            <Link
              to={`/monitors/${monitor.id}`}
              className="flex flex-col gap-2 rounded-lg border border-border bg-surface px-4 py-3 hover:border-primary-300"
            >
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <p className="truncate font-semibold text-neutral-900">{monitor.name}</p>
                  <p className="truncate text-xs text-neutral-500">{hostOf(monitor.url)}</p>
                </div>
                <StatusBadge status={displayStatus(monitor)} />
              </div>
              <div className="flex gap-4 text-xs text-neutral-600">
                <span className="tabular-nums">{formatLatency(monitor.last_latency_ms)}</span>
                {monitor.last_status_code !== null && <span className="tabular-nums">HTTP {monitor.last_status_code}</span>}
                <LastCheck value={monitor.last_checked_at} />
              </div>
            </Link>
          </li>
        ))}
      </ul>
    </>
  )
}
