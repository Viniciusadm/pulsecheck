import { useQuery } from '@tanstack/react-query'
import { CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { getLatencyTrend } from '../../../api/dashboard.ts'
import { queryKeys } from '../../../api/queryKeys.ts'
import type { LatencyTrendBucket } from '../../../api/types.ts'
import { t } from '../../../i18n/index.ts'
import { AXIS_LINE, AXIS_TICK, BUCKET_MINUTES, GRID_STROKE, PRIMARY, PRIMARY_DARK, TOOLTIP_STYLE, bucketTickLabel } from '../../../lib/chart.ts'
import { formatDateTime, formatLatency } from '../../../lib/format.ts'
import { EmptyChart, QueryContent } from './QueryContent.tsx'
import { REFRESH_INTERVAL_MS } from './refresh.ts'
import type { WidgetProps } from './types.ts'

export function LatencyTrendWidget({ period }: WidgetProps) {
  const trend = useQuery({ queryKey: queryKeys.dashboardMetric('latency-trend', period), queryFn: () => getLatencyTrend(period), refetchInterval: REFRESH_INTERVAL_MS })

  return <QueryContent query={trend}>{(buckets) => <LatencyTrendChart buckets={buckets} bucketMinutes={BUCKET_MINUTES[period]} />}</QueryContent>
}

function LatencyTrendChart({ buckets, bucketMinutes }: { buckets: LatencyTrendBucket[]; bucketMinutes: number }) {
  if (!buckets.some((bucket) => bucket.average_latency_ms !== null)) {
    return <EmptyChart message={t('dashboard.charts.noChecks')} />
  }

  const series = [
    { key: 'average_latency_ms', label: t('dashboard.charts.average'), color: PRIMARY },
    { key: 'p95_latency_ms', label: t('dashboard.charts.p95'), color: PRIMARY_DARK },
  ]

  return (
    <div className="flex h-full flex-col gap-2">
      <ul className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-neutral-600">
        {series.map((line) => (
          <li key={line.key} className="flex items-center gap-1.5">
            <span aria-hidden="true" className="h-0.5 w-4 rounded-full" style={{ backgroundColor: line.color }} />
            {line.label}
          </li>
        ))}
      </ul>
      <div className="min-h-0 flex-1" role="img" aria-label={t('dashboard.charts.latencyChart')}>
        <ResponsiveContainer width="100%" height="100%">
          <LineChart data={buckets} margin={{ top: 8, right: 8, bottom: 0, left: 0 }}>
            <CartesianGrid stroke={GRID_STROKE} vertical={false} />
            <XAxis
              dataKey="bucket_start"
              tickFormatter={(value: string) => bucketTickLabel(value, bucketMinutes)}
              minTickGap={32}
              tick={AXIS_TICK}
              tickLine={false}
              axisLine={AXIS_LINE}
            />
            <YAxis width={56} tickFormatter={(value: number) => formatLatency(value)} tick={AXIS_TICK} tickLine={false} axisLine={false} />
            <Tooltip
              labelFormatter={(value) => formatDateTime(String(value))}
              formatter={(value, name) => [formatLatency(Number(value)), series.find((line) => line.key === name)?.label ?? name]}
              contentStyle={TOOLTIP_STYLE}
            />
            {series.map((line) => (
              <Line
                key={line.key}
                type="monotone"
                dataKey={line.key}
                stroke={line.color}
                strokeWidth={2}
                connectNulls={false}
                dot={false}
                activeDot={{ r: 4, stroke: '#ffffff', strokeWidth: 2 }}
                isAnimationActive={false}
              />
            ))}
          </LineChart>
        </ResponsiveContainer>
      </div>
    </div>
  )
}
