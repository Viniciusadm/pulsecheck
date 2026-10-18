import type { StatsPeriod } from '../api/types.ts'
import { formatDate } from './format.ts'

export const PRIMARY = '#349ef4'

export const PRIMARY_DARK = '#195698'

export const GRID_STROKE = '#e1e5ea'

export const AXIS_TICK = { fill: '#5f6876', fontSize: 12 }

export const AXIS_LINE = { stroke: '#cdd2da' }

export const TOOLTIP_STYLE = { borderRadius: 6, borderColor: '#e1e5ea', fontSize: 13 }

export const BUCKET_MINUTES: Record<StatsPeriod, number> = { '24h': 15, '7d': 60, '30d': 360 }

export function bucketTickLabel(value: string, bucketMinutes: number): string {
  if (bucketMinutes >= 360) {
    return formatDate(value, { month: 'short', day: 'numeric' })
  }

  return formatDate(value, bucketMinutes >= 60 ? { weekday: 'short', hour: 'numeric' } : { hour: 'numeric', minute: '2-digit' })
}
