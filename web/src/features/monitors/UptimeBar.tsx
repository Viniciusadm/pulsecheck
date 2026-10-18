import type { UptimeDay } from '../../api/types.ts'
import { t } from '../../i18n/index.ts'
import { formatPercentage, formatShortDate } from '../../lib/format.ts'

const NO_DATA_PATTERN = 'repeating-linear-gradient(135deg, #e1e5ea 0 3px, #f4f6f8 3px 6px)'

function dayClass(day: UptimeDay): string {
  if (day.uptime_percentage === null) {
    return ''
  }

  if (day.uptime_percentage === 100) {
    return 'bg-status-up'
  }

  return day.uptime_percentage >= 95 ? 'bg-status-pending' : 'bg-status-down'
}

function dayLabel(day: UptimeDay): string {
  const date = formatShortDate(day.date)

  return day.uptime_percentage === null
    ? t('monitors.detail.dailyUptimeNoData', { date })
    : t('monitors.detail.dailyUptimeDay', { date, uptime: formatPercentage(day.uptime_percentage), total: day.total_checks })
}

export function UptimeBar({ days }: { days: UptimeDay[] }) {
  const withData = days.filter((day) => day.uptime_percentage !== null).length

  return (
    <div className="flex flex-col gap-2">
      <ol className="flex h-10 gap-0.5" aria-label={t('monitors.detail.dailyUptimeSummary', { withData })}>
        {days.map((day) => (
          <li
            key={day.date}
            title={dayLabel(day)}
            aria-label={dayLabel(day)}
            className={`flex-1 rounded-sm ${dayClass(day)}`}
            style={day.uptime_percentage === null ? { backgroundImage: NO_DATA_PATTERN } : undefined}
          />
        ))}
      </ol>
      <div className="flex flex-wrap gap-4 text-xs text-neutral-600">
        <span className="inline-flex items-center gap-1.5">
          <span className="size-3 rounded-sm bg-status-up" aria-hidden="true" />
          {t('monitors.detail.legendFull')}
        </span>
        <span className="inline-flex items-center gap-1.5">
          <span className="size-3 rounded-sm bg-status-pending" aria-hidden="true" />
          {t('monitors.detail.legendPartial')}
        </span>
        <span className="inline-flex items-center gap-1.5">
          <span className="size-3 rounded-sm" style={{ backgroundImage: NO_DATA_PATTERN }} aria-hidden="true" />
          {t('monitors.detail.legendNoData')}
        </span>
      </div>
    </div>
  )
}
