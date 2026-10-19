import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import type { UptimeDay } from '../../api/types.ts'
import { UptimeBar } from './UptimeBar.tsx'

const days: UptimeDay[] = [
  { date: '2026-10-16', uptime_percentage: 100, total_checks: 10, successful_checks: 10, degraded_checks: 0, maintenance_checks: 0 },
  { date: '2026-10-17', uptime_percentage: 90, total_checks: 10, successful_checks: 9, degraded_checks: 0, maintenance_checks: 0 },
  { date: '2026-10-18', uptime_percentage: null, total_checks: 0, successful_checks: 0, degraded_checks: 0, maintenance_checks: 0 },
]

describe('UptimeBar', () => {
  it('moves between days with the arrow keys and shows the focused day', async () => {
    const user = userEvent.setup()
    render(<UptimeBar days={days} />)
    const items = screen.getAllByRole('listitem')

    await user.tab()
    expect(items[2]).toHaveFocus()

    await user.keyboard('{ArrowLeft}')
    expect(items[1]).toHaveFocus()
    expect(screen.getByText(items[1]?.getAttribute('aria-label') ?? '', { selector: 'p' })).toBeVisible()

    await user.keyboard('{Home}')
    expect(items[0]).toHaveFocus()
    expect(items.filter((item) => item.tabIndex === 0)).toEqual([items[0]])
  })
})
