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
