import { Area, AreaChart, CartesianGrid, ReferenceLine, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import type { LatencySeries } from '../../api/types.ts'
import { t } from '../../i18n/index.ts'
import { AXIS_LINE, AXIS_TICK, GRID_PROPS, PRIMARY, TOOLTIP_STYLE, bucketTickLabel } from '../../lib/chart.ts'
import { formatDateTime, formatLatency } from '../../lib/format.ts'

type LatencyChartProps = {
  series: LatencySeries
  className?: string
  thresholdMs?: number | null
}

export function LatencyChart({ series, className = 'h-56', thresholdMs = null }: LatencyChartProps) {
  const hasData = series.buckets.some((bucket) => bucket.average_latency_ms !== null)

  if (!hasData) {
    return <p className={`flex items-center justify-center text-neutral-500 ${className}`}>{t('monitors.detail.latencyChartEmpty')}</p>
  }

  return (
    <div className={`w-full ${className}`} role="img" aria-label={t('monitors.detail.latencyChart')}>
      <ResponsiveContainer width="100%" height="100%">
        <AreaChart data={series.buckets} margin={{ top: 12, right: 16, bottom: 0, left: 0 }}>
          <CartesianGrid {...GRID_PROPS} />
          <XAxis
            dataKey="bucket_start"
            tickFormatter={(value: string) => bucketTickLabel(value, series.bucket_minutes)}
            minTickGap={32}
            tick={AXIS_TICK}
            tickLine={false}
            axisLine={AXIS_LINE}
          />
          <YAxis
            width={56}
            tickFormatter={(value: number) => formatLatency(value)}
            tick={AXIS_TICK}
            tickLine={false}
            axisLine={false}
          />
          <Tooltip
            labelFormatter={(value) => formatDateTime(String(value))}
            formatter={(value) => [t('monitors.detail.latencyTooltip', { value: formatLatency(Number(value)) }), '']}
            separator=""
            contentStyle={TOOLTIP_STYLE}
          />
          {thresholdMs !== null && (
            <ReferenceLine
              y={thresholdMs}
              stroke="var(--color-status-degraded)"
              strokeDasharray="4 4"
              ifOverflow="extendDomain"
              label={{ value: t('monitors.detail.latencyThreshold', { value: formatLatency(thresholdMs) }), position: 'insideTopRight', fill: 'var(--color-status-degraded)', fontSize: 12 }}
            />
          )}
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
