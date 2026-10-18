import { useQuery } from '@tanstack/react-query'
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { getIncidentTimeline } from '../../../api/dashboard.ts'
import { queryKeys } from '../../../api/queryKeys.ts'
import type { IncidentTimelineBucket } from '../../../api/types.ts'
import { t } from '../../../i18n/index.ts'
import { AXIS_LINE, AXIS_TICK, BUCKET_MINUTES, GRID_STROKE, PRIMARY, TOOLTIP_STYLE, bucketTickLabel } from '../../../lib/chart.ts'
import { formatDateTime } from '../../../lib/format.ts'
import { EmptyChart, QueryContent } from './QueryContent.tsx'
import { REFRESH_INTERVAL_MS } from './refresh.ts'
import type { WidgetProps } from './types.ts'

export function IncidentTimelineWidget({ period }: WidgetProps) {
  const timeline = useQuery({
    queryKey: queryKeys.dashboardMetric('incident-timeline', period),
    queryFn: () => getIncidentTimeline(period),
    refetchInterval: REFRESH_INTERVAL_MS,
  })

  return <QueryContent query={timeline}>{(buckets) => <TimelineChart buckets={buckets} bucketMinutes={BUCKET_MINUTES[period]} />}</QueryContent>
}

function TimelineChart({ buckets, bucketMinutes }: { buckets: IncidentTimelineBucket[]; bucketMinutes: number }) {
  const total = buckets.reduce((sum, bucket) => sum + bucket.incidents, 0)

  if (total === 0) {
    return <EmptyChart message={t('dashboard.charts.noIncidents')} />
  }

  return (
    <div className="flex h-full flex-col gap-2">
      <p className="flex items-baseline gap-2">
        <span className="text-2xl font-semibold tabular-nums text-neutral-900">{total}</span>
        <span className="text-xs text-neutral-500">{t('dashboard.charts.incidentsInPeriod')}</span>
      </p>
      <div className="min-h-0 flex-1" role="img" aria-label={t('dashboard.charts.incidentChart', { count: total })}>
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={buckets} margin={{ top: 8, right: 8, bottom: 0, left: 0 }} barCategoryGap={2}>
            <CartesianGrid stroke={GRID_STROKE} vertical={false} />
            <XAxis
              dataKey="bucket_start"
              tickFormatter={(value: string) => bucketTickLabel(value, bucketMinutes)}
              minTickGap={32}
              tick={AXIS_TICK}
              tickLine={false}
              axisLine={AXIS_LINE}
            />
            <YAxis width={32} allowDecimals={false} tick={AXIS_TICK} tickLine={false} axisLine={false} />
            <Tooltip
              cursor={{ fill: '#eef0f3' }}
              labelFormatter={(value) => formatDateTime(String(value))}
              formatter={(value) => [value, t('dashboard.charts.incidents')]}
              contentStyle={TOOLTIP_STYLE}
            />
            <Bar dataKey="incidents" fill={PRIMARY} maxBarSize={24} radius={[4, 4, 0, 0]} isAnimationActive={false} />
          </BarChart>
        </ResponsiveContainer>
      </div>
    </div>
  )
}
