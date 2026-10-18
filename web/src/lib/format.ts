const dateTimeFormatter = new Intl.DateTimeFormat('en', {
  dateStyle: 'medium',
  timeStyle: 'short',
})

export function formatDateTime(value: string | Date): string {
  return dateTimeFormatter.format(typeof value === 'string' ? new Date(value) : value)
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

const relativeTimeFormatter = new Intl.RelativeTimeFormat('en', { numeric: 'auto' })

const relativeUnits: [Intl.RelativeTimeFormatUnit, number][] = [
  ['day', 86_400],
  ['hour', 3_600],
  ['minute', 60],
  ['second', 1],
]

export function formatRelativeTime(value: string | Date, now: Date = new Date()): string {
  const date = typeof value === 'string' ? new Date(value) : value
  const elapsedSeconds = Math.round((date.getTime() - now.getTime()) / 1000)

  if (Math.abs(elapsedSeconds) < 10) {
    return relativeTimeFormatter.format(0, 'second')
  }

  const [unit, seconds] = relativeUnits.find(([, size]) => Math.abs(elapsedSeconds) >= size) ?? ['second', 1]

  return relativeTimeFormatter.format(Math.round(elapsedSeconds / seconds), unit)
}

export function formatShortDate(value: string): string {
  return new Intl.DateTimeFormat('en', { month: 'short', day: 'numeric', timeZone: 'UTC' }).format(new Date(`${value}T00:00:00Z`))
}
