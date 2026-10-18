import { useQuery } from '@tanstack/react-query'
import { isAxiosError } from 'axios'
import { Link } from 'react-router'
import { getLatencySeries, getMonitor } from '../../../api/monitors.ts'
import { queryKeys } from '../../../api/queryKeys.ts'
import { t } from '../../../i18n/index.ts'
import { LatencyChart } from '../../monitors/LatencyChart.tsx'
import { EmptyChart, QueryContent } from './QueryContent.tsx'
import { REFRESH_INTERVAL_MS } from './refresh.ts'
import type { WidgetProps } from './types.ts'

export function MonitorLatencyWidget({ widget, period }: WidgetProps) {
  const monitorId = widget.options.monitor_id ?? 0
  const monitor = useQuery({ queryKey: queryKeys.monitor(monitorId), queryFn: () => getMonitor(monitorId), enabled: monitorId > 0 })
  const latency = useQuery({
    queryKey: queryKeys.latency(monitorId, period),
    queryFn: () => getLatencySeries(monitorId, period),
    enabled: monitor.isSuccess,
    refetchInterval: REFRESH_INTERVAL_MS,
  })

  const isUnavailable = isAxiosError(monitor.error) && monitor.error.response?.status === 404

  if (monitorId === 0 || isUnavailable) {
    return <EmptyChart message={t('dashboard.monitorUnavailable')} />
  }

  return (
    <QueryContent query={monitor}>
      {(data) => (
        <div className="flex h-full flex-col gap-2">
          <Link to={`/monitors/${data.id}`} className="truncate font-medium text-neutral-800 hover:text-primary-700 hover:underline">
            {data.name}
          </Link>
          <div className="min-h-0 flex-1">
            <QueryContent query={latency}>{(series) => <LatencyChart series={series} className="h-full" />}</QueryContent>
          </div>
        </div>
      )}
    </QueryContent>
  )
}
