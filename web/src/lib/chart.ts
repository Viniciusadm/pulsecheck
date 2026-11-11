import type { StatsPeriod } from '../api/types.ts'
import { formatDate } from './format.ts'

export const PRIMARY = 'var(--color-primary-500)'

export const PRIMARY_DARK = 'var(--color-primary-800)'

export const GRID_STROKE = 'var(--color-neutral-200)'

export const AXIS_TICK = { fill: 'var(--color-neutral-500)', fontSize: 12 }

export const AXIS_LINE = { stroke: 'var(--color-neutral-300)' }

export const TOOLTIP_STYLE = {
  borderRadius: 6,
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
