import { useQuery } from '@tanstack/react-query'
import { Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { getUptimeTrend } from '../../../api/dashboard.ts'
import { queryKeys } from '../../../api/queryKeys.ts'
import type { UptimeTrendBucket } from '../../../api/types.ts'
import { t } from '../../../i18n/index.ts'
import { AXIS_LINE, AXIS_TICK, BUCKET_MINUTES, GRID_STROKE, PRIMARY, TOOLTIP_STYLE, bucketTickLabel } from '../../../lib/chart.ts'
import { formatDateTime, formatPercentage } from '../../../lib/format.ts'
import { EmptyChart, QueryContent } from './QueryContent.tsx'
import { REFRESH_INTERVAL_MS } from './refresh.ts'
import { overallUptime } from './uptime.ts'
import type { WidgetProps } from './types.ts'

export function UptimeTrendWidget({ period }: WidgetProps) {
  const trend = useQuery({ queryKey: queryKeys.dashboardMetric('uptime-trend', period), queryFn: () => getUptimeTrend(period), refetchInterval: REFRESH_INTERVAL_MS })

  return <QueryContent query={trend}>{(buckets) => <UptimeChart buckets={buckets} bucketMinutes={BUCKET_MINUTES[period]} />}</QueryContent>
}

function UptimeChart({ buckets, bucketMinutes }: { buckets: UptimeTrendBucket[]; bucketMinutes: number }) {
  const uptime = overallUptime(buckets)

  if (uptime === null) {
    return <EmptyChart message={t('dashboard.charts.noChecks')} />
  }

  const lowest = Math.min(...buckets.flatMap((bucket) => (bucket.uptime_percentage === null ? [] : [bucket.uptime_percentage])))
  const floor = Math.max(0, Math.floor((lowest - 1) / 5) * 5)
  const ticks = Array.from({ length: 6 }, (_, index) => floor + (index * (100 - floor)) / 5)

  return (
    <div className="flex h-full flex-col gap-2">
      <p className="flex items-baseline gap-2">
        <span className="text-2xl font-semibold tabular-nums text-neutral-900">{formatPercentage(uptime)}</span>
        <span className="text-xs text-neutral-500">{t('dashboard.charts.uptimeInPeriod')}</span>
      </p>
      <div className="min-h-0 flex-1" role="img" aria-label={t('dashboard.charts.uptimeChart', { value: formatPercentage(uptime) })}>
        <ResponsiveContainer width="100%" height="100%">
          <AreaChart data={buckets} margin={{ top: 8, right: 8, bottom: 0, left: 0 }}>
            <CartesianGrid stroke={GRID_STROKE} vertical={false} />
            <XAxis
              dataKey="bucket_start"
              tickFormatter={(value: string) => bucketTickLabel(value, bucketMinutes)}
              minTickGap={32}
              tick={AXIS_TICK}
              tickLine={false}
              axisLine={AXIS_LINE}
            />
            <YAxis width={48} domain={[floor, 100]} ticks={ticks} tickFormatter={(value: number) => `${value}%`} tick={AXIS_TICK} tickLine={false} axisLine={false} />
            <Tooltip
              labelFormatter={(value) => formatDateTime(String(value))}
              formatter={(value) => [formatPercentage(Number(value)), t('dashboard.charts.uptime')]}
              contentStyle={TOOLTIP_STYLE}
            />
            <Area
              type="monotone"
              dataKey="uptime_percentage"
              stroke={PRIMARY}
              strokeWidth={2}
              fill={PRIMARY}
              fillOpacity={0.1}
              connectNulls
              dot={false}
              activeDot={{ r: 4, stroke: '#ffffff', strokeWidth: 2 }}
              isAnimationActive={false}
            />
          </AreaChart>
        </ResponsiveContainer>
      </div>
    </div>
  )
}
