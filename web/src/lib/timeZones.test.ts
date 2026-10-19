import { describe, expect, it } from 'vitest'
import { isoToZonedInput, zonedInputToIso } from './timeZones.ts'

describe('zoned time conversion', () => {
  it('converts wall-clock input in a time zone to UTC and back', () => {
    expect(zonedInputToIso('2026-10-17T09:30', 'America/Sao_Paulo')).toBe('2026-10-17T12:30:00.000Z')
    expect(isoToZonedInput('2026-10-17T12:30:00.000Z', 'America/Sao_Paulo')).toBe('2026-10-17T09:30')
    expect(zonedInputToIso('2026-10-17T09:30', 'UTC')).toBe('2026-10-17T09:30:00.000Z')
  })

  it('handles daylight saving offsets', () => {
    expect(zonedInputToIso('2026-07-01T12:00', 'Europe/Berlin')).toBe('2026-07-01T10:00:00.000Z')
    expect(zonedInputToIso('2026-12-01T12:00', 'Europe/Berlin')).toBe('2026-12-01T11:00:00.000Z')
  })
})
