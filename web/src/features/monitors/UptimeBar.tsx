import { useRef, useState, type KeyboardEvent } from 'react'
import type { UptimeDay } from '../../api/types.ts'

export type UptimeBarDay = Pick<UptimeDay, 'date' | 'uptime_percentage'> & Partial<Pick<UptimeDay, 'total_checks' | 'degraded_checks' | 'maintenance_checks'>>
import { t } from '../../i18n/index.ts'
import { formatPercentage, formatShortDate } from '../../lib/format.ts'

const NO_DATA_PATTERN = 'repeating-linear-gradient(135deg, #e1e5ea 0 3px, #f4f6f8 3px 6px)'

function dayClass(day: UptimeBarDay): string {
  if (day.uptime_percentage === null) {
    return (day.maintenance_checks ?? 0) > 0 ? 'bg-status-maintenance' : ''
  }

  if (day.uptime_percentage === 100) {
    return 'bg-status-up'
  }

  return day.uptime_percentage >= 95 ? 'bg-status-pending' : 'bg-status-down'
}

function dayLabel(day: UptimeBarDay): string {
  const date = formatShortDate(day.date)

  if (day.uptime_percentage === null) {
    const label = t('monitors.detail.dailyUptimeNoData', { date })

    return (day.maintenance_checks ?? 0) > 0 ? `${label} · ${t('status.maintenance')}` : label
  }

  if (day.total_checks === undefined) {
    return t('monitors.detail.dailyUptimeDayShort', { date, uptime: formatPercentage(day.uptime_percentage) })
  }

  const label = t('monitors.detail.dailyUptimeDay', { date, uptime: formatPercentage(day.uptime_percentage), total: day.total_checks })

  return (day.degraded_checks ?? 0) > 0 ? `${label} · ${t('monitors.detail.dailyUptimeSlow', { count: day.degraded_checks ?? 0 })}` : label
}

const NAVIGATION_KEYS: Record<string, (index: number, last: number) => number> = {
  ArrowLeft: (index) => Math.max(0, index - 1),
  ArrowRight: (index, last) => Math.min(last, index + 1),
  Home: () => 0,
  End: (_index, last) => last,
}

export function UptimeBar({ days, label }: { days: UptimeBarDay[]; label?: string }) {
  const withData = days.filter((day) => day.uptime_percentage !== null).length
  const lastIndex = days.length - 1
  const [focusIndex, setFocusIndex] = useState(lastIndex)
  const [highlightedIndex, setHighlightedIndex] = useState<number | null>(null)
  const itemRefs = useRef<(HTMLLIElement | null)[]>([])
  const tabbableIndex = Math.min(focusIndex, lastIndex)
  const highlightedDay = highlightedIndex === null ? undefined : days[highlightedIndex]

  const handleKeyDown = (event: KeyboardEvent<HTMLOListElement>) => {
    const move = NAVIGATION_KEYS[event.key]

    if (!move) {
      return
    }

    event.preventDefault()
    const next = move(tabbableIndex, lastIndex)
    setFocusIndex(next)
    itemRefs.current[next]?.focus()
  }

  return (
    <div className="flex flex-col gap-2">
      <ol
        className="flex h-10 gap-0.5"
        aria-label={label ?? t('monitors.detail.dailyUptimeSummary', { withData })}
        onKeyDown={handleKeyDown}
        onMouseLeave={() => setHighlightedIndex(null)}
      >
        {days.map((day, index) => (
          <li
            key={day.date}
            ref={(element) => {
              itemRefs.current[index] = element
            }}
            tabIndex={index === tabbableIndex ? 0 : -1}
            title={dayLabel(day)}
            aria-label={dayLabel(day)}
            onFocus={() => {
              setFocusIndex(index)
              setHighlightedIndex(index)
            }}
            onBlur={() => setHighlightedIndex(null)}
            onMouseEnter={() => setHighlightedIndex(index)}
            className={`flex-1 rounded-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-1 ${dayClass(day)}`}
            style={day.uptime_percentage === null && !day.maintenance_checks ? { backgroundImage: NO_DATA_PATTERN } : undefined}
          />
        ))}
      </ol>
      <p className="min-h-4 text-xs text-neutral-700" aria-hidden="true">
        {highlightedDay ? dayLabel(highlightedDay) : ''}
      </p>
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
          <span className="size-3 rounded-sm bg-status-down" aria-hidden="true" />
          {t('monitors.detail.legendLow')}
        </span>
        <span className="inline-flex items-center gap-1.5">
          <span className="size-3 rounded-sm" style={{ backgroundImage: NO_DATA_PATTERN }} aria-hidden="true" />
          {t('monitors.detail.legendNoData')}
        </span>
        {days.some((day) => day.uptime_percentage === null && (day.maintenance_checks ?? 0) > 0) && (
          <span className="inline-flex items-center gap-1.5">
            <span className="size-3 rounded-sm bg-status-maintenance" aria-hidden="true" />
            {t('status.maintenance')}
          </span>
        )}
        <span className="ml-auto">{t('monitors.detail.legendUtc')}</span>
      </div>
    </div>
  )
}
