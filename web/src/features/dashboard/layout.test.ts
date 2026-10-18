import { describe, expect, it } from 'vitest'
import type { DashboardWidget } from '../../api/types.ts'
import { addWidget, applyGridPositions, inReadingOrder, removeWidget } from './layout.ts'
import { overallUptime } from './widgets/uptime.ts'

const widgets: DashboardWidget[] = [
  { id: 'a', type: 'uptime_trend', x: 0, y: 0, w: 8, h: 4, options: {} },
  { id: 'b', type: 'status_distribution', x: 8, y: 0, w: 4, h: 6, options: {} },
]

describe('dashboard layout', () => {
  it('adds a widget below the lowest one with its default size', () => {
    const next = addWidget(widgets, 'monitor_latency', { monitor_id: 3 })

    expect(next).toHaveLength(3)
    expect(next[2]).toMatchObject({ type: 'monitor_latency', x: 0, y: 6, w: 6, h: 4, options: { monitor_id: 3 } })
    expect(next[2].id).not.toBe(addWidget(widgets, 'monitor_latency').at(-1)?.id)
  })

  it('removes a widget by id', () => {
    expect(removeWidget(widgets, 'a').map((widget) => widget.id)).toEqual(['b'])
  })

  it('applies grid positions and keeps the same array when nothing moved', () => {
    expect(applyGridPositions(widgets, [{ i: 'a', x: 0, y: 0, w: 8, h: 4 }])).toBe(widgets)

    const moved = applyGridPositions(widgets, [{ i: 'a', x: 4, y: 6, w: 8, h: 5 }])

    expect(moved[0]).toEqual({ ...widgets[0], x: 4, y: 6, h: 5 })
    expect(moved[1]).toBe(widgets[1])
  })

  it('orders widgets row by row for stacked screens', () => {
    expect(inReadingOrder([{ ...widgets[1], y: 4 }, widgets[0], { ...widgets[1], id: 'c', x: 8, y: 0 }]).map((widget) => widget.id)).toEqual(['a', 'c', 'b'])
  })

  it('computes overall uptime like the backend', () => {
    expect(overallUptime([])).toBeNull()
    expect(overallUptime([{ bucket_start: '', checks: 10_000, failed_checks: 1, uptime_percentage: null }])).toBe(99.99)
    expect(overallUptime([{ bucket_start: '', checks: 4, failed_checks: 0, uptime_percentage: 100 }])).toBe(100)
  })
})
