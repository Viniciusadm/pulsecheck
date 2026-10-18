import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router'
import { getDashboardSummary } from '../../../api/dashboard.ts'
import { queryKeys } from '../../../api/queryKeys.ts'
import type { DashboardSummary } from '../../../api/types.ts'
import { DegradedIcon, DownIcon, PausedIcon, UpIcon } from '../../../components/ui/icons.tsx'
import { t } from '../../../i18n/index.ts'
import { QueryContent } from './QueryContent.tsx'
import { useRefreshInterval } from './refresh.ts'

export function StatusSummaryWidget() {
  const refetchInterval = useRefreshInterval()
  const summary = useQuery({ queryKey: queryKeys.summary, queryFn: getDashboardSummary, refetchInterval })

  return <QueryContent query={summary}>{(data) => <SummaryCards summary={data} />}</QueryContent>
}

function SummaryCards({ summary }: { summary: DashboardSummary }) {
  const cards = [
    {
      label: t('dashboard.summary.total'),
      value: summary.total,
      icon: null,
      tone: 'text-neutral-900',
      to: '/monitors',
      note: summary.pending > 0 ? t('dashboard.summary.pending', { count: summary.pending }) : null,
    },
    { label: t('dashboard.summary.up'), value: summary.up, icon: <UpIcon />, tone: 'text-status-up', to: '/monitors?status=up', note: null },
    { label: t('dashboard.summary.degraded'), value: summary.degraded, icon: <DegradedIcon />, tone: 'text-status-degraded', to: '/monitors?status=degraded', note: null },
    { label: t('dashboard.summary.down'), value: summary.down, icon: <DownIcon />, tone: 'text-status-down', to: '/monitors?status=down', note: null },
    { label: t('dashboard.summary.paused'), value: summary.paused, icon: <PausedIcon />, tone: 'text-status-paused', to: '/monitors?status=paused', note: null },
  ]

  return (
    <dl className="grid grid-cols-2 gap-3 @2xl:grid-cols-5">
      {cards.map(({ label, value, icon, tone, to, note }) => (
        <div
          key={label}
          className={`relative flex flex-col gap-0.5 rounded-lg border bg-surface px-4 py-2 hover:border-primary-300 ${
            tone === 'text-status-down' && value > 0 ? 'border-status-down/40' : 'border-border'
          }`}
        >
          <dt className={`flex items-center gap-1.5 text-sm/5 font-medium ${tone}`}>
            {icon}
            <Link to={to} className="after:absolute after:inset-0 after:rounded-lg focus-visible:outline-none focus-visible:after:ring-2 focus-visible:after:ring-primary-200">
              {label}
            </Link>
          </dt>
          <dd className="flex min-w-0 items-baseline gap-2">
            <span className="text-2xl font-semibold tabular-nums text-neutral-900">{value}</span>
            {note && <span className="truncate text-xs text-neutral-500">{note}</span>}
          </dd>
        </div>
      ))}
    </dl>
  )
}
