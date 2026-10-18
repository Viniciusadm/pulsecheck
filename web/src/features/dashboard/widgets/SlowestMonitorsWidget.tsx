import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router'
import { getSlowestMonitors } from '../../../api/dashboard.ts'
import { queryKeys } from '../../../api/queryKeys.ts'
import { t } from '../../../i18n/index.ts'
import { formatLatency } from '../../../lib/format.ts'
import { EmptyChart, QueryContent } from './QueryContent.tsx'
import { RankedBars } from './RankedBars.tsx'
import { useRefreshInterval } from './refresh.ts'
import type { WidgetProps } from './types.ts'

export function SlowestMonitorsWidget({ period }: WidgetProps) {
  const refetchInterval = useRefreshInterval()
  const slowest = useQuery({
    queryKey: queryKeys.dashboardMetric('slowest-monitors', period),
    queryFn: () => getSlowestMonitors(period),
    refetchInterval,
  })

  return (
    <QueryContent query={slowest}>
      {(monitors) =>
        monitors.length === 0 ? (
          <EmptyChart message={t('dashboard.charts.noChecks')} />
        ) : (
          <RankedBars
            items={monitors.map((monitor) => ({
              key: String(monitor.monitor_id),
              label: (
                <Link to={`/monitors/${monitor.monitor_id}`} className="hover:text-primary-700 hover:underline">
                  {monitor.name}
                </Link>
              ),
              value: monitor.average_latency_ms,
              valueLabel: formatLatency(monitor.average_latency_ms),
            }))}
          />
        )
      }
    </QueryContent>
  )
}
