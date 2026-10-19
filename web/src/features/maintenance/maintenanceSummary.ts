import type { MaintenanceWindow } from '../../api/types.ts'
import { t } from '../../i18n/index.ts'
import { formatDateTime } from '../../lib/format.ts'

export const WEEKDAYS = [1, 2, 3, 4, 5, 6, 7]

const WEEKDAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as const

export function weekdayLabel(day: number): string {
  return t(`maintenance.weekdays.${WEEKDAY_KEYS[day - 1] ?? 'mon'}`)
}

export function scheduleSummary(window: MaintenanceWindow): string {
  if (window.type === 'one_off') {
    return t('maintenance.oneOffSummary', { from: formatDateTime(window.starts_at ?? ''), to: formatDateTime(window.ends_at ?? '') })
  }

  return t('maintenance.recurringSummary', {
    days: (window.weekdays ?? []).map(weekdayLabel).join(', '),
    from: window.start_time ?? '',
    to: window.end_time ?? '',
    timezone: window.timezone ?? '',
  })
}
