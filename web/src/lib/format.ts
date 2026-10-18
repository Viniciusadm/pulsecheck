import { getLocale } from '../i18n/index.ts'

let displayTimeZone: string | undefined

export function setDisplayTimeZone(timeZone: string | null | undefined): void {
  if (!timeZone) {
    displayTimeZone = undefined

    return
  }

  try {
    new Intl.DateTimeFormat('en', { timeZone })
    displayTimeZone = timeZone
  } catch {
    displayTimeZone = undefined
  }
}

export function getDisplayTimeZone(): string | undefined {
  return displayTimeZone
}

const dateTimeFormatters = new Map<string, Intl.DateTimeFormat>()

function dateTimeFormatter(options: Intl.DateTimeFormatOptions): Intl.DateTimeFormat {
  const resolvedOptions = { timeZone: displayTimeZone, ...options }
  const cacheKey = `${getLocale()}|${JSON.stringify(resolvedOptions)}`
  let formatter = dateTimeFormatters.get(cacheKey)

  if (!formatter) {
    formatter = new Intl.DateTimeFormat(getLocale(), resolvedOptions)
    dateTimeFormatters.set(cacheKey, formatter)
  }

  return formatter
}

function toDate(value: string | Date): Date {
  return typeof value === 'string' ? new Date(value) : value
}

export function formatDate(value: string | Date, options: Intl.DateTimeFormatOptions): string {
  return dateTimeFormatter(options).format(toDate(value))
}

export function formatDateTime(value: string | Date): string {
  return formatDate(value, { dateStyle: 'medium', timeStyle: 'short' })
}

export function formatTime(value: string | Date): string {
  return formatDate(value, { timeStyle: 'short' })
}

export function formatLatency(milliseconds: number | null): string {
  if (milliseconds === null) {
    return '—'
  }

  return milliseconds >= 1000 ? `${(milliseconds / 1000).toFixed(2)} s` : `${milliseconds} ms`
}

export function formatDuration(totalSeconds: number): string {
  const days = Math.floor(totalSeconds / 86_400)
  const hours = Math.floor((totalSeconds % 86_400) / 3_600)
  const minutes = Math.floor((totalSeconds % 3_600) / 60)
  const seconds = totalSeconds % 60

  const parts = [
    [days, 'd'],
    [hours, 'h'],
    [minutes, 'm'],
    [seconds, 's'],
  ].filter(([amount]) => amount !== 0)

  if (parts.length === 0) {
    return '0s'
  }

  return parts
    .slice(0, 2)
    .map(([amount, unit]) => `${amount}${unit}`)
    .join(' ')
}

export function formatPercentage(value: number | null): string {
  return value === null ? '—' : `${value.toFixed(value === 100 ? 0 : 2)}%`
}

const relativeUnits: [Intl.RelativeTimeFormatUnit, number][] = [
  ['day', 86_400],
  ['hour', 3_600],
  ['minute', 60],
  ['second', 1],
]

export function formatRelativeTime(value: string | Date, now: Date = new Date()): string {
  const formatter = new Intl.RelativeTimeFormat(getLocale(), { numeric: 'auto' })
  const elapsedSeconds = Math.round((toDate(value).getTime() - now.getTime()) / 1000)

  if (Math.abs(elapsedSeconds) < 10) {
    return formatter.format(0, 'second')
  }

  const [unit, seconds] = relativeUnits.find(([, size]) => Math.abs(elapsedSeconds) >= size) ?? ['second', 1]

  return formatter.format(Math.round(elapsedSeconds / seconds), unit)
}

export function formatShortDate(value: string): string {
  return formatDate(`${value}T00:00:00Z`, { month: 'short', day: 'numeric', timeZone: 'UTC' })
}
