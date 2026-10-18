import { afterEach, describe, expect, it } from 'vitest'
import { formatDateTime, formatDuration, formatLatency, formatPercentage, formatRelativeTime, formatShortDate, formatTime, getDisplayTimeZone, setDisplayTimeZone } from './format.ts'

describe('formatLatency', () => {
  it('uses milliseconds below one second', () => {
    expect(formatLatency(245)).toBe('245 ms')
  })

  it('uses seconds from one second', () => {
    expect(formatLatency(1530)).toBe('1.53 s')
  })

  it('shows a dash without data', () => {
    expect(formatLatency(null)).toBe('—')
  })
})

describe('formatDuration', () => {
  it.each([
    [0, '0s'],
    [45, '45s'],
    [180, '3m'],
    [3930, '1h 5m'],
    [90_061, '1d 1h'],
  ])('formats %i seconds as %s', (seconds, expected) => {
    expect(formatDuration(seconds)).toBe(expected)
  })
})

describe('formatPercentage', () => {
  it('shows no data as a dash, never 100%', () => {
    expect(formatPercentage(null)).toBe('—')
  })

  it('keeps two decimals except for 100', () => {
    expect(formatPercentage(99.5)).toBe('99.50%')
    expect(formatPercentage(100)).toBe('100%')
  })
})

describe('formatRelativeTime', () => {
  const now = new Date('2026-10-17T12:00:00Z')

  it.each([
    ['2026-10-17T11:59:55Z', 'now'],
    ['2026-10-17T11:59:30Z', '30 seconds ago'],
    ['2026-10-17T11:57:00Z', '3 minutes ago'],
    ['2026-10-17T09:00:00Z', '3 hours ago'],
    ['2026-10-15T12:00:00Z', '2 days ago'],
  ])('formats %s', (value, expected) => {
    expect(formatRelativeTime(value, now)).toBe(expected)
  })
})

describe('display time zone', () => {
  afterEach(() => setDisplayTimeZone(undefined))

  it('formats dates in the instance time zone', () => {
    setDisplayTimeZone('America/Sao_Paulo')

    expect(formatTime('2026-10-17T12:00:00Z')).toBe('9:00 AM')
    expect(formatDateTime('2026-10-17T02:00:00Z')).toBe('Oct 16, 2026, 11:00 PM')
  })

  it('ignores an unknown time zone', () => {
    setDisplayTimeZone('UTC')
    setDisplayTimeZone('Not/A_Zone')

    expect(getDisplayTimeZone()).toBeUndefined()
    expect(() => formatTime('2026-10-17T12:00:00Z')).not.toThrow()
  })

  it('keeps calendar days independent of the time zone', () => {
    setDisplayTimeZone('Pacific/Kiritimati')

    expect(formatShortDate('2026-10-17')).toBe('Oct 17')
  })
})
