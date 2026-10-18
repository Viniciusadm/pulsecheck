import { useQuery } from '@tanstack/react-query'
import { getErrorBreakdown } from '../../../api/dashboard.ts'
import { queryKeys } from '../../../api/queryKeys.ts'
import { t } from '../../../i18n/index.ts'
import { EmptyChart, QueryContent } from './QueryContent.tsx'
import { RankedBars } from './RankedBars.tsx'
import { useRefreshInterval } from './refresh.ts'
import type { WidgetProps } from './types.ts'

export function ErrorBreakdownWidget({ period }: WidgetProps) {
  const refetchInterval = useRefreshInterval()
  const breakdown = useQuery({
    queryKey: queryKeys.dashboardMetric('error-breakdown', period),
    queryFn: () => getErrorBreakdown(period),
    refetchInterval,
  })

  return (
    <QueryContent query={breakdown}>
      {(items) =>
        items.length === 0 ? (
          <EmptyChart message={t('dashboard.charts.noFailures')} />
        ) : (
          <RankedBars
            items={items.map((item) => ({
              key: item.error_type,
              label: t(`monitors.errorTypes.${item.error_type}`),
              value: item.failed_checks,
              valueLabel: t('dashboard.charts.failedChecks', { count: item.failed_checks }),
            }))}
          />
        )
      }
    </QueryContent>
  )
}
