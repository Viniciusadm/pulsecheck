const FALLBACK_TIME_ZONE = 'UTC'

export function browserTimeZone(): string {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || FALLBACK_TIME_ZONE
  } catch {
    return FALLBACK_TIME_ZONE
  }
}

export function timeZoneOptions(selected?: string): string[] {
  let zones: string[]

  try {
    zones = Intl.supportedValuesOf('timeZone')
  } catch {
    zones = []
  }

  const options = new Set([FALLBACK_TIME_ZONE, ...zones])

  if (selected) {
    options.add(selected)
  }

  return [...options].sort((first, second) => first.localeCompare(second))
}

function offsetMilliseconds(timestamp: number, timeZone: string): number {
  const parts = Object.fromEntries(
    new Intl.DateTimeFormat('en-US', { timeZone, hourCycle: 'h23', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit' })
      .formatToParts(new Date(timestamp))
      .map((part) => [part.type, part.value]),
  )

  return Date.UTC(Number(parts.year), Number(parts.month) - 1, Number(parts.day), Number(parts.hour), Number(parts.minute), Number(parts.second)) - timestamp
}

export function zonedInputToIso(value: string, timeZone: string): string {
  const [date, time = '00:00'] = value.split('T')
  const [year, month, day] = date.split('-').map(Number)
  const [hour, minute] = time.split(':').map(Number)
  const wallClock = Date.UTC(year, month - 1, day, hour, minute)
  const firstGuess = wallClock - offsetMilliseconds(wallClock, timeZone)

  return new Date(wallClock - offsetMilliseconds(firstGuess, timeZone)).toISOString()
}

export function isoToZonedInput(value: string, timeZone: string): string {
  const timestamp = new Date(value).getTime()

  return new Date(timestamp + offsetMilliseconds(timestamp, timeZone)).toISOString().slice(0, 16)
}
