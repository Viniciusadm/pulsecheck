import { useQuery } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts'
import { getDashboardSummary } from '../../../api/dashboard.ts'
import { queryKeys } from '../../../api/queryKeys.ts'
import type { DashboardSummary } from '../../../api/types.ts'
import { DownIcon, PausedIcon, PendingIcon, UpIcon } from '../../../components/ui/icons.tsx'
import { t } from '../../../i18n/index.ts'
import { TOOLTIP_STYLE } from '../../../lib/chart.ts'
import { EmptyChart, QueryContent } from './QueryContent.tsx'
import { useRefreshInterval } from './refresh.ts'

type Slice = { key: string; label: string; value: number; color: string; icon: ReactNode; tone: string }

export function StatusDistributionWidget() {
  const refetchInterval = useRefreshInterval()
  const summary = useQuery({ queryKey: queryKeys.summary, queryFn: getDashboardSummary, refetchInterval })

  return <QueryContent query={summary}>{(data) => <Distribution summary={data} />}</QueryContent>
}

function Distribution({ summary }: { summary: DashboardSummary }) {
  if (summary.total === 0) {
    return <EmptyChart message={t('dashboard.charts.noMonitors')} />
  }

  const slices: Slice[] = [
    { key: 'up', label: t('status.up'), value: summary.up, color: '#15803d', icon: <UpIcon />, tone: 'text-status-up' },
    { key: 'down', label: t('status.down'), value: summary.down, color: '#dc2626', icon: <DownIcon />, tone: 'text-status-down' },
    { key: 'pending', label: t('status.pending'), value: summary.pending, color: '#b45309', icon: <PendingIcon />, tone: 'text-status-pending' },
    { key: 'paused', label: t('status.paused'), value: summary.paused, color: '#5f6876', icon: <PausedIcon />, tone: 'text-status-paused' },
  ]
  const visibleSlices = slices.filter((slice) => slice.value > 0)

  return (
    <div className="flex h-full flex-col items-center gap-4 @xs:flex-row">
      <div className="relative h-full min-h-32 w-full max-w-44 flex-1" role="img" aria-label={t('dashboard.charts.statusDistribution')}>
        <ResponsiveContainer width="100%" height="100%">
          <PieChart>
            <Pie
              data={visibleSlices}
              dataKey="value"
              nameKey="label"
              innerRadius="62%"
              outerRadius="100%"
              stroke="#ffffff"
              strokeWidth={visibleSlices.length > 1 ? 2 : 0}
              isAnimationActive={false}
            >
              {visibleSlices.map((slice) => (
                <Cell key={slice.key} fill={slice.color} />
              ))}
            </Pie>
            <Tooltip contentStyle={TOOLTIP_STYLE} />
          </PieChart>
        </ResponsiveContainer>
        <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
          <span className="text-2xl font-semibold tabular-nums text-neutral-900">{summary.total}</span>
          <span className="text-xs text-neutral-500">{t('dashboard.summary.total')}</span>
        </div>
      </div>
      <ul className="flex flex-col gap-2 self-stretch @xs:justify-center">
        {slices.map((slice) => (
          <li key={slice.key} className="flex items-center justify-between gap-4">
            <span className={`flex items-center gap-1.5 font-medium ${slice.tone}`}>
              {slice.icon}
              <span className="text-neutral-800">{slice.label}</span>
            </span>
            <span className="tabular-nums text-neutral-700">{slice.value}</span>
          </li>
        ))}
      </ul>
    </div>
  )
}
