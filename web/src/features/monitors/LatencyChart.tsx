import { Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import type { LatencySeries } from '../../api/types.ts'
import { t } from '../../i18n/index.ts'
import { formatDate, formatDateTime, formatLatency } from '../../lib/format.ts'

const PRIMARY = '#349ef4'

function tickLabel(value: string, bucketMinutes: number): string {
  if (bucketMinutes >= 360) {
    return formatDate(value, { month: 'short', day: 'numeric' })
  }

  return formatDate(value, bucketMinutes >= 60 ? { weekday: 'short', hour: 'numeric' } : { hour: 'numeric', minute: '2-digit' })
}

export function LatencyChart({ series }: { series: LatencySeries }) {
  const hasData = series.buckets.some((bucket) => bucket.average_latency_ms !== null)

  if (!hasData) {
    return <p className="flex h-56 items-center justify-center text-neutral-500">{t('monitors.detail.latencyChartEmpty')}</p>
  }

  return (
    <div className="h-56 w-full" role="img" aria-label={t('monitors.detail.latencyChart')}>
      <ResponsiveContainer width="100%" height="100%">
        <AreaChart data={series.buckets} margin={{ top: 12, right: 16, bottom: 0, left: 0 }}>
          <CartesianGrid stroke="#e1e5ea" vertical={false} />
          <XAxis
            dataKey="bucket_start"
            tickFormatter={(value: string) => tickLabel(value, series.bucket_minutes)}
            minTickGap={32}
            tick={{ fill: '#5f6876', fontSize: 12 }}
            tickLine={false}
            axisLine={{ stroke: '#cdd2da' }}
          />
          <YAxis
            width={56}
            tickFormatter={(value: number) => formatLatency(value)}
            tick={{ fill: '#5f6876', fontSize: 12 }}
            tickLine={false}
            axisLine={false}
          />
          <Tooltip
            labelFormatter={(value) => formatDateTime(String(value))}
            formatter={(value) => [t('monitors.detail.latencyTooltip', { value: formatLatency(Number(value)) }), '']}
            separator=""
            contentStyle={{ borderRadius: 6, borderColor: '#e1e5ea', fontSize: 13 }}
          />
          <Area
            type="monotone"
            dataKey="average_latency_ms"
            stroke={PRIMARY}
            strokeWidth={2}
            fill={PRIMARY}
            fillOpacity={0.12}
            connectNulls={false}
            dot={{ r: 3, fill: PRIMARY, strokeWidth: 0 }}
            activeDot={{ r: 4 }}
            isAnimationActive={false}
          />
        </AreaChart>
      </ResponsiveContainer>
    </div>
  )
}
