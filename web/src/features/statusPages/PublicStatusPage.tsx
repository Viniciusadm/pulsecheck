import { useQuery } from '@tanstack/react-query'
import { isAxiosError } from 'axios'
import { useParams } from 'react-router'
import { getPublicStatusPage } from '../../api/statusPages.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import type { PublicMonitorStatus, PublicOverallStatus, PublicStatusPage as PublicStatusPageData } from '../../api/types.ts'
import { Badge } from '../../components/ui/Badge.tsx'
import { Card } from '../../components/ui/Card.tsx'
import { ErrorState } from '../../components/ui/ErrorState.tsx'
import { RelativeTime } from '../../components/ui/RelativeTime.tsx'
import { Skeleton } from '../../components/ui/Skeleton.tsx'
import { t } from '../../i18n/index.ts'
import { formatDateTime, formatDuration, formatLatency, formatPercentage } from '../../lib/format.ts'
import { UptimeBar } from '../monitors/UptimeBar.tsx'

const REFRESH_INTERVAL_MS = 60_000

const statusClasses: Record<PublicMonitorStatus, string> = {
  up: 'bg-status-up-soft text-status-up',
  degraded: 'bg-status-degraded-soft text-status-degraded',
  down: 'bg-status-down-soft text-status-down',
  pending: 'bg-status-pending-soft text-status-pending',
  paused: 'bg-status-paused-soft text-status-paused',
  maintenance: 'bg-status-maintenance-soft text-status-maintenance',
}

const overallClasses: Record<PublicOverallStatus, string> = {
  operational: 'border-status-up/30 bg-status-up-soft text-status-up',
  degraded: 'border-status-degraded/30 bg-status-degraded-soft text-status-degraded',
  partial_outage: 'border-status-down/30 bg-status-down-soft text-status-down',
  major_outage: 'border-status-down/30 bg-status-down-soft text-status-down',
  maintenance: 'border-status-maintenance/30 bg-status-maintenance-soft text-status-maintenance',
  unknown: 'border-border bg-neutral-50 text-neutral-700',
}

export function PublicStatusPage() {
  const slug = useParams().slug ?? ''
  const page = useQuery({
    queryKey: queryKeys.publicStatusPage(slug),
    queryFn: () => getPublicStatusPage(slug),
    refetchInterval: REFRESH_INTERVAL_MS,
  })

  if (page.isPending) {
    return <Skeleton className="h-64" />
  }

  if (page.isError) {
    return isAxiosError(page.error) && page.error.response?.status === 404 ? (
      <p className="rounded-lg border border-dashed border-neutral-300 px-6 py-10 text-neutral-700">{t('publicStatus.notFound')}</p>
    ) : (
      <ErrorState onRetry={() => void page.refetch()} />
    )
  }

  return <StatusPageContent page={page.data} />
}

function StatusPageContent({ page }: { page: PublicStatusPageData }) {
  const monitorNames = new Map(page.monitors.map((monitor) => [monitor.key, monitor.name]))

  return (
    <div className="flex flex-col gap-8">
      <header className="flex flex-col gap-2">
        <h1 className="text-2xl font-semibold text-neutral-900">{page.name}</h1>
        {page.description && <p className="text-neutral-600">{page.description}</p>}
      </header>

      <p role="status" className={`rounded-lg border px-4 py-3 text-lg font-semibold ${overallClasses[page.overall_status]}`}>
        {t(`publicStatus.overall.${page.overall_status}`)}
      </p>

      <ul className="flex flex-col gap-4">
        {page.monitors.map((monitor) => (
          <li key={monitor.key}>
            <Card className="flex flex-col gap-3 px-4 py-4">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="font-semibold text-neutral-900">{monitor.name}</h2>
                <div className="flex flex-wrap items-center gap-3 text-sm text-neutral-600">
                  {monitor.latency_ms !== null && <span>{t('publicStatus.latency', { value: formatLatency(monitor.latency_ms) })}</span>}
                  <span>
                    {monitor.uptime_percentage === null
                      ? t('publicStatus.noData')
                      : t('publicStatus.uptime', { uptime: formatPercentage(monitor.uptime_percentage) })}
                  </span>
                  <Badge className={statusClasses[monitor.status]}>{t(`publicStatus.statuses.${monitor.status}`)}</Badge>
                </div>
              </div>
              <UptimeBar days={monitor.days} label={t('publicStatus.history', { days: page.uptime_days })} />
            </Card>
          </li>
        ))}
      </ul>

      <section className="flex flex-col gap-3" aria-labelledby="public-incidents">
        <h2 id="public-incidents" className="text-lg font-semibold text-neutral-900">
          {t('publicStatus.incidents')}
        </h2>
        {page.incidents.length === 0 ? (
          <p className="text-neutral-600">{t('publicStatus.noIncidents')}</p>
        ) : (
          <ul className="divide-y divide-border rounded-lg border border-border bg-surface">
            {page.incidents.map((incident) => (
              <li key={`${incident.monitor_key}-${incident.started_at}`} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                <div className="flex flex-col">
                  <span className="font-medium text-neutral-900">{monitorNames.get(incident.monitor_key)}</span>
                  <time dateTime={incident.started_at} className="text-xs text-neutral-500">
                    {formatDateTime(incident.started_at)}
                  </time>
                </div>
                {incident.resolved_at === null ? (
                  <Badge className="bg-status-down-soft text-status-down">{t('publicStatus.ongoing')}</Badge>
                ) : (
                  <span className="text-sm text-neutral-600">{t('publicStatus.lasted', { duration: formatDuration(incident.duration_seconds) })}</span>
                )}
              </li>
            ))}
          </ul>
        )}
      </section>

      <p className="text-xs text-neutral-500">
        <RelativeTime value={page.generated_at} format={(time) => t('publicStatus.updated', { time })} />
      </p>
    </div>
  )
}
