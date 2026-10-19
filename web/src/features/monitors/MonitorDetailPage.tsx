import { useQuery } from '@tanstack/react-query'
import { isAxiosError } from 'axios'
import { lazy, Suspense, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { generalErrorMessage } from '../../api/errors.ts'
import { getLatencySeries, getMonitor, getMonitorStats, getUptimeHistory, listMonitorIncidents } from '../../api/monitors.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import type { Monitor, MonitorStats, StatsPeriod } from '../../api/types.ts'
import { StatusBadge } from '../../components/ui/Badge.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { Card } from '../../components/ui/Card.tsx'
import { ConfirmDialog } from '../../components/ui/ConfirmDialog.tsx'
import { EmptyState } from '../../components/ui/EmptyState.tsx'
import { ErrorState } from '../../components/ui/ErrorState.tsx'
import { RelativeTime } from '../../components/ui/RelativeTime.tsx'
import { PeriodSelector } from '../../components/ui/PeriodSelector.tsx'
import { Skeleton } from '../../components/ui/Skeleton.tsx'
import { useToast } from '../../components/ui/toast.ts'
import { t } from '../../i18n/index.ts'
import { formatDateTime, formatLatency, formatPercentage, formatTime } from '../../lib/format.ts'
import { displayStatus } from '../../lib/monitor.ts'
import { canManageResources } from '../../lib/permissions.ts'
import { useAuth } from '../auth/AuthContext.ts'
import { MonitorMaintenanceSection } from '../maintenance/MonitorMaintenanceSection.tsx'
import { CheckHistory } from './CheckHistory.tsx'
import { MonitorBadgeCard } from './MonitorBadgeCard.tsx'
import { HeartbeatPanel } from './HeartbeatPanel.tsx'
import { IncidentList } from './IncidentList.tsx'
import { MonitorFormDialog } from './MonitorFormDialog.tsx'
import { usePollingInterval } from './realtimeConnection.ts'
import { UptimeBar } from './UptimeBar.tsx'
import { useDeleteMonitor, usePauseMonitor, useResumeMonitor } from './useMonitorMutations.ts'

const LatencyChart = lazy(() => import('./LatencyChart.tsx').then((module) => ({ default: module.LatencyChart })))

const REFRESH_INTERVAL_MS = 60_000

export function MonitorDetailPage() {
  const monitorId = Number(useParams().id)
  const isValidId = Number.isInteger(monitorId) && monitorId > 0
  const refetchInterval = usePollingInterval(REFRESH_INTERVAL_MS)
  const monitor = useQuery({
    queryKey: queryKeys.monitor(monitorId),
    queryFn: () => getMonitor(monitorId),
    refetchInterval,
    enabled: isValidId,
  })

  if (!isValidId || monitor.isError) {
    const isNotFound = !isValidId || (isAxiosError(monitor.error) && monitor.error.response?.status === 404)

    return isNotFound ? (
      <EmptyState
        title={t('monitors.detail.notFound')}
        action={
          <Link to="/monitors" className="font-medium text-primary-700 hover:underline">
            {t('monitors.detail.back')}
          </Link>
        }
      />
    ) : (
      <ErrorState onRetry={() => void monitor.refetch()} />
    )
  }

  if (monitor.isPending) {
    return (
      <div className="flex flex-col gap-6" aria-busy="true">
        <Skeleton className="h-24" />
        <Skeleton className="h-64" />
      </div>
    )
  }

  return <MonitorDetail monitor={monitor.data} />
}

function MonitorDetail({ monitor }: { monitor: Monitor }) {
  const [period, setPeriod] = useState<StatsPeriod>('24h')
  const [isEditing, setIsEditing] = useState(false)
  const [isConfirmingDelete, setIsConfirmingDelete] = useState(false)
  const navigate = useNavigate()
  const { user } = useAuth()
  const canManage = canManageResources(user)
  const { showToast } = useToast()
  const pauseMutation = usePauseMonitor(monitor.id)
  const resumeMutation = useResumeMonitor(monitor.id)
  const deleteMutation = useDeleteMonitor(monitor.id)
  const refetchInterval = usePollingInterval(REFRESH_INTERVAL_MS)

  const stats = useQuery({ queryKey: queryKeys.stats(monitor.id, period), queryFn: () => getMonitorStats(monitor.id, period), refetchInterval })
  const latency = useQuery({ queryKey: queryKeys.latency(monitor.id, period), queryFn: () => getLatencySeries(monitor.id, period), refetchInterval })
  const uptimeHistory = useQuery({ queryKey: queryKeys.uptimeHistory(monitor.id), queryFn: () => getUptimeHistory(monitor.id, 30) })
  const incidents = useQuery({ queryKey: queryKeys.monitorIncidents(monitor.id, 1), queryFn: () => listMonitorIncidents(monitor.id, 1), refetchInterval })

  const showError = (error: unknown) => showToast(generalErrorMessage(error) ?? t('common.genericError'), 'error')

  const togglePause = () => {
    if (monitor.is_paused) {
      resumeMutation.mutate(undefined, { onSuccess: () => showToast(t('monitors.detail.resumed')), onError: showError })
    } else {
      pauseMutation.mutate(undefined, { onSuccess: () => showToast(t('monitors.detail.paused')), onError: showError })
    }
  }

  const confirmDelete = () =>
    deleteMutation.mutate(undefined, {
      onSuccess: () => {
        showToast(t('monitors.detail.deleted'))
        navigate('/monitors', { replace: true })
      },
      onError: (error) => {
        setIsConfirmingDelete(false)
        showError(error)
      },
    })

  return (
    <div className="flex flex-col gap-8">
      <div className="flex flex-col gap-4">
        <Link to="/monitors" className="self-start text-sm font-medium text-primary-700 hover:underline">
          {t('monitors.detail.back')}
        </Link>
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="flex min-w-0 flex-col gap-2">
            <div className="flex flex-wrap items-center gap-3">
              <h1 className="text-2xl font-semibold text-neutral-900">{monitor.name}</h1>
              <StatusBadge status={displayStatus(monitor)} />
            </div>
            {monitor.url ? (
              <a href={monitor.url} target="_blank" rel="noopener noreferrer" className="break-all text-primary-700 hover:underline">
                {monitor.url}
              </a>
            ) : (
              <p className="break-all text-neutral-700">{monitor.display_target}</p>
            )}
            <p className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-neutral-600">
              <span className="font-medium text-neutral-700">{t(`monitors.types.${monitor.type}`)}</span>
              <span>{t('monitors.detail.interval', { minutes: monitor.interval_minutes })}</span>
              {monitor.type !== 'heartbeat' && <span>{t('monitors.detail.timeout', { seconds: monitor.timeout_seconds })}</span>}
              {(monitor.type === 'http' || monitor.type === 'keyword') && (
                <span>
                  {t('monitors.detail.request', {
                    method: monitor.options.method ?? 'GET',
                    codes: (monitor.options.expected_status_codes ?? ['200-399']).join(', '),
                  })}
                </span>
              )}
              {monitor.maintenance_ends_at && (
                <span className="font-medium text-status-maintenance">
                  {t('monitors.detail.inMaintenance', { time: formatDateTime(monitor.maintenance_ends_at) })}
                </span>
              )}
              {monitor.certificate_expires_at && (
                <span>{t('monitors.detail.certificateExpires', { date: formatDateTime(monitor.certificate_expires_at) })}</span>
              )}
              {monitor.last_checked_at && (
                <RelativeTime value={monitor.last_checked_at} format={(time) => t('monitors.detail.lastCheck', { time })} />
              )}
              {!monitor.is_paused && monitor.next_check_at && (
                <time dateTime={monitor.next_check_at} title={formatDateTime(monitor.next_check_at)}>
                  {t('monitors.detail.nextCheck', { time: formatTime(monitor.next_check_at) })}
                </time>
              )}
            </p>
          </div>
          {canManage && (
            <div className="flex flex-wrap gap-2">
              <Button variant="secondary" onClick={() => setIsEditing(true)}>
                {t('monitors.detail.edit')}
              </Button>
              <Button variant="secondary" onClick={togglePause} isLoading={pauseMutation.isPending || resumeMutation.isPending}>
                {monitor.is_paused ? t('monitors.detail.resume') : t('monitors.detail.pause')}
              </Button>
              <Button variant="danger" onClick={() => setIsConfirmingDelete(true)}>
                {t('monitors.detail.delete')}
              </Button>
            </div>
          )}
        </div>
      </div>

      {monitor.heartbeat_url && <HeartbeatPanel monitor={monitor} heartbeatUrl={monitor.heartbeat_url} />}

      <section className="flex flex-col gap-4" aria-labelledby="monitor-metrics">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <h2 id="monitor-metrics" className="text-lg font-semibold text-neutral-900">
            {t('monitors.detail.latencyChart')}
          </h2>
          <PeriodSelector
            value={period}
            label={t('monitors.detail.period')}
            optionLabel={(option) => t(`monitors.detail.periods.${option}`)}
            onChange={setPeriod}
          />
        </div>

        {stats.isPending && <Skeleton className="h-24" />}
        {stats.isError && <ErrorState onRetry={() => void stats.refetch()} />}
        {stats.data && <StatsCards stats={stats.data} />}

        <Card className="px-4 py-4">
          {latency.isPending && <Skeleton className="h-56" />}
          {latency.isError && <ErrorState onRetry={() => void latency.refetch()} />}
          {latency.data && (
            <Suspense fallback={<Skeleton className="h-56" />}>
              <LatencyChart series={latency.data} thresholdMs={monitor.latency_threshold_ms} />
            </Suspense>
          )}
        </Card>
      </section>

      <section className="flex flex-col gap-3" aria-labelledby="daily-uptime">
        <h2 id="daily-uptime" className="text-lg font-semibold text-neutral-900">
          {t('monitors.detail.dailyUptime')}
        </h2>
        {uptimeHistory.isPending && <Skeleton className="h-10" />}
        {uptimeHistory.isError && <ErrorState onRetry={() => void uptimeHistory.refetch()} />}
        {uptimeHistory.data && <UptimeBar days={uptimeHistory.data} />}
      </section>

      <section className="flex flex-col gap-3" aria-labelledby="monitor-incidents">
        <h2 id="monitor-incidents" className="text-lg font-semibold text-neutral-900">
          {t('monitors.incidents.heading')}
        </h2>
        {incidents.isPending && <Skeleton className="h-24" />}
        {incidents.isError && <ErrorState onRetry={() => void incidents.refetch()} />}
        {incidents.data && incidents.data.data.length === 0 && <p className="text-neutral-600">{t('monitors.incidents.empty')}</p>}
        {incidents.data && incidents.data.data.length > 0 && <IncidentList incidents={incidents.data.data} />}
      </section>

      <MonitorMaintenanceSection monitorId={monitor.id} canManage={canManage} />

      {canManage && <MonitorBadgeCard monitor={monitor} />}

      <CheckHistory monitorId={monitor.id} />

      <p className="text-xs text-neutral-500">{t('monitors.detail.notice')}</p>

      <MonitorFormDialog isOpen={isEditing} onClose={() => setIsEditing(false)} monitor={monitor} />
      <ConfirmDialog
        isOpen={isConfirmingDelete}
        title={t('monitors.detail.deleteTitle', { name: monitor.name })}
        description={t('monitors.detail.deleteDescription')}
        confirmLabel={t('monitors.detail.deleteConfirm')}
        pendingLabel={t('monitors.detail.deleting')}
        isPending={deleteMutation.isPending}
        onConfirm={confirmDelete}
        onClose={() => setIsConfirmingDelete(false)}
      />
    </div>
  )
}

function StatsCards({ stats }: { stats: MonitorStats }) {
  return (
    <dl className="grid grid-cols-1 gap-3 sm:grid-cols-3">
      <div className="flex flex-col gap-1 rounded-lg border border-border bg-surface px-4 py-4">
        <dt className="text-sm font-medium text-neutral-600">{t('monitors.detail.uptime')}</dt>
        <dd className="text-2xl font-semibold tabular-nums text-neutral-900">
          {stats.uptime_percentage === null ? t('monitors.detail.noData') : formatPercentage(stats.uptime_percentage)}
        </dd>
        <dd className="text-xs text-neutral-500">
          {stats.total_checks === 0
            ? t('monitors.detail.noChecks')
            : t('monitors.detail.checksSummary', { successful: stats.successful_checks, total: stats.total_checks })}
        </dd>
      </div>
      <div className="flex flex-col gap-1 rounded-lg border border-border bg-surface px-4 py-4">
        <dt className="text-sm font-medium text-neutral-600">{t('monitors.detail.averageLatency')}</dt>
        <dd className="text-2xl font-semibold tabular-nums text-neutral-900">{formatLatency(stats.average_latency_ms)}</dd>
      </div>
      <div className="flex flex-col gap-1 rounded-lg border border-border bg-surface px-4 py-4">
        <dt className="text-sm font-medium text-neutral-600">{t('monitors.detail.p95Latency')}</dt>
        <dd className="text-2xl font-semibold tabular-nums text-neutral-900">{formatLatency(stats.p95_latency_ms)}</dd>
      </div>
    </dl>
  )
}
