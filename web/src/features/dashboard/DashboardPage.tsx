import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { getDashboardSummary, listRecentIncidents } from '../../api/dashboard.ts'
import { listMonitors } from '../../api/monitors.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import type { DashboardSummary, Monitor } from '../../api/types.ts'
import { StatusBadge } from '../../components/ui/Badge.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { EmptyState } from '../../components/ui/EmptyState.tsx'
import { ErrorState } from '../../components/ui/ErrorState.tsx'
import { DownIcon, PausedIcon, UpIcon } from '../../components/ui/icons.tsx'
import { RelativeTime } from '../../components/ui/RelativeTime.tsx'
import { Skeleton } from '../../components/ui/Skeleton.tsx'
import { t } from '../../i18n/index.ts'
import { hostOf } from '../../lib/monitor.ts'
import { canManageResources } from '../../lib/permissions.ts'
import { useAuth } from '../auth/AuthContext.ts'
import { IncidentList } from '../monitors/IncidentList.tsx'
import { MonitorFormDialog } from '../monitors/MonitorFormDialog.tsx'

const REFRESH_INTERVAL_MS = 30_000

export function DashboardPage() {
  const [isFormOpen, setIsFormOpen] = useState(false)
  const { user } = useAuth()
  const canManage = canManageResources(user)
  const navigate = useNavigate()
  const summary = useQuery({ queryKey: queryKeys.summary, queryFn: getDashboardSummary, refetchInterval: REFRESH_INTERVAL_MS })
  const downMonitors = useQuery({
    queryKey: queryKeys.monitorList(1, 'down'),
    queryFn: () => listMonitors({ status: 'down' }),
    refetchInterval: REFRESH_INTERVAL_MS,
  })
  const incidents = useQuery({ queryKey: queryKeys.recentIncidents, queryFn: () => listRecentIncidents(5), refetchInterval: REFRESH_INTERVAL_MS })

  const header = (
    <div className="flex flex-wrap items-center justify-between gap-3">
      <h1 className="text-2xl font-semibold text-neutral-900">{t('dashboard.title')}</h1>
      {canManage && summary.data && summary.data.total > 0 && <Button onClick={() => setIsFormOpen(true)}>{t('monitors.add')}</Button>}
    </div>
  )

  const formDialog = (
    <MonitorFormDialog isOpen={isFormOpen} onClose={() => setIsFormOpen(false)} onSaved={(monitor) => navigate(`/monitors/${monitor.id}`)} />
  )

  if (summary.isPending || downMonitors.isPending) {
    return (
      <div className="flex flex-col gap-6" aria-busy="true">
        {header}
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
          {Array.from({ length: 4 }, (_, index) => (
            <Skeleton key={index} className="h-24" />
          ))}
        </div>
        <Skeleton className="h-32" />
      </div>
    )
  }

  if (summary.isError || downMonitors.isError) {
    return (
      <div className="flex flex-col gap-6">
        {header}
        <ErrorState
          onRetry={() => {
            void summary.refetch()
            void downMonitors.refetch()
          }}
        />
      </div>
    )
  }

  if (summary.data.total === 0) {
    return (
      <div className="flex flex-col gap-6">
        {header}
        <EmptyState
          title={t('dashboard.empty.title')}
          description={t('dashboard.empty.description')}
          action={canManage ? <Button onClick={() => setIsFormOpen(true)}>{t('dashboard.empty.action')}</Button> : undefined}
        />
        {formDialog}
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-8">
      {header}
      <SummaryCards summary={summary.data} />

      <section className="flex flex-col gap-3" aria-labelledby="dashboard-attention">
        <div className="flex items-center justify-between gap-3">
          <h2 id="dashboard-attention" className="text-lg font-semibold text-neutral-900">
            {t('dashboard.attentionHeading')}
          </h2>
          {downMonitors.data.meta.total > downMonitors.data.data.length && (
            <Link to="/monitors?status=down" className="font-medium text-primary-700 hover:underline">
              {t('dashboard.viewAllDown')}
            </Link>
          )}
        </div>
        <AttentionList monitors={downMonitors.data.data} />
      </section>

      <section className="flex flex-col gap-3" aria-labelledby="dashboard-incidents">
        <h2 id="dashboard-incidents" className="text-lg font-semibold text-neutral-900">
          {t('dashboard.incidentsHeading')}
        </h2>
        {incidents.isPending && <Skeleton className="h-32" />}
        {incidents.isError && <ErrorState onRetry={() => void incidents.refetch()} />}
        {incidents.data && incidents.data.length === 0 && <p className="text-neutral-600">{t('dashboard.noIncidents')}</p>}
        {incidents.data && incidents.data.length > 0 && <IncidentList incidents={incidents.data} showMonitor />}
      </section>

      {formDialog}
    </div>
  )
}

function SummaryCards({ summary }: { summary: DashboardSummary }) {
  const cards = [
    { label: t('dashboard.summary.total'), value: summary.total, icon: null, tone: 'text-neutral-900', to: '/monitors' },
    { label: t('dashboard.summary.up'), value: summary.up, icon: <UpIcon />, tone: 'text-status-up', to: '/monitors?status=up' },
    { label: t('dashboard.summary.down'), value: summary.down, icon: <DownIcon />, tone: 'text-status-down', to: '/monitors?status=down' },
    { label: t('dashboard.summary.paused'), value: summary.paused, icon: <PausedIcon />, tone: 'text-status-paused', to: '/monitors?status=paused' },
  ]

  return (
    <div className="flex flex-col gap-2">
      <dl className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        {cards.map(({ label, value, icon, tone, to }) => (
          <div
            key={label}
            className={`relative flex flex-col gap-1 rounded-lg border bg-surface px-4 py-4 hover:border-primary-300 ${
              tone === 'text-status-down' && value > 0 ? 'border-status-down/40' : 'border-border'
            }`}
          >
            <dt className={`flex items-center gap-1.5 text-sm font-medium ${tone}`}>
              {icon}
              <Link to={to} className="after:absolute after:inset-0 after:rounded-lg focus-visible:outline-none focus-visible:after:ring-2 focus-visible:after:ring-primary-200">
                {label}
              </Link>
            </dt>
            <dd className="text-2xl font-semibold tabular-nums text-neutral-900">{value}</dd>
          </div>
        ))}
      </dl>
      {summary.pending > 0 && <p className="text-xs text-neutral-500">{t('dashboard.summary.pending', { count: summary.pending })}</p>}
    </div>
  )
}

function AttentionList({ monitors }: { monitors: Monitor[] }) {
  if (monitors.length === 0) {
    return (
      <p className="flex items-center gap-2 rounded-lg border border-border bg-surface px-4 py-3 font-medium text-status-up">
        <UpIcon />
        {t('dashboard.allHealthy')}
      </p>
    )
  }

  return (
    <ul className="divide-y divide-border rounded-lg border border-status-down/40 bg-surface">
      {monitors.map((monitor) => (
        <li key={monitor.id} className="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:gap-4">
          <div className="min-w-0 flex-1">
            <Link to={`/monitors/${monitor.id}`} className="font-semibold text-neutral-900 hover:text-primary-700 hover:underline">
              {monitor.name}
            </Link>
            <p className="truncate text-xs text-neutral-500">{hostOf(monitor.url)}</p>
          </div>
          <div className="flex items-center gap-3 text-sm text-neutral-600">
            {monitor.last_status_code !== null && <span className="tabular-nums">HTTP {monitor.last_status_code}</span>}
            {monitor.last_checked_at !== null && <RelativeTime value={monitor.last_checked_at} />}
            <StatusBadge status="down" />
          </div>
        </li>
      ))}
    </ul>
  )
}
