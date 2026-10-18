import { describe, expect, it } from 'vitest'
import { formatDuration, formatLatency, formatPercentage, formatRelativeTime } from './format.ts'

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
