import type { StatsPeriod } from '../api/types.ts'
import { formatDate } from './format.ts'

export const PRIMARY = 'var(--color-primary-500)'

export const PRIMARY_DARK = 'var(--color-primary-800)'

export const GRID_PROPS = { stroke: 'var(--color-neutral-200)', strokeDasharray: '2 4' }

export const AXIS_TICK = { fill: 'var(--color-neutral-500)', fontSize: 12, fontVariantNumeric: 'tabular-nums' }

export const AXIS_LINE = { stroke: 'var(--color-neutral-300)' }

export const TOOLTIP_STYLE = {
  borderRadius: 4,
  borderColor: 'var(--color-border)',
  backgroundColor: 'var(--color-surface)',
  color: 'var(--color-neutral-900)',
  fontSize: 13,
}

export const BUCKET_MINUTES: Record<StatsPeriod, number> = { '24h': 15, '7d': 60, '30d': 360 }

export function bucketTickLabel(value: string, bucketMinutes: number): string {
  if (bucketMinutes >= 360) {
    return formatDate(value, { month: 'short', day: 'numeric' })
  }

  return formatDate(value, bucketMinutes >= 60 ? { weekday: 'short', hour: 'numeric' } : { hour: 'numeric', minute: '2-digit' })
}
