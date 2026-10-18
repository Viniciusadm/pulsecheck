import type { ReactNode } from 'react'

export type RankedBar = {
  key: string
  label: ReactNode
  value: number
  valueLabel: string
}

export function RankedBars({ items }: { items: RankedBar[] }) {
  const largest = Math.max(1, ...items.map((item) => item.value))

  return (
    <ul className="flex flex-col gap-3">
      {items.map((item) => (
        <li key={item.key} className="flex flex-col gap-1.5">
          <div className="flex items-baseline justify-between gap-3">
            <span className="min-w-0 truncate text-neutral-800">{item.label}</span>
            <span className="shrink-0 tabular-nums text-neutral-600">{item.valueLabel}</span>
          </div>
          <div className="h-2 rounded-full bg-neutral-100" aria-hidden="true">
            <div className="h-2 rounded-full bg-primary-500" style={{ width: `${Math.max((item.value / largest) * 100, 2)}%` }} />
          </div>
        </li>
      ))}
    </ul>
  )
}
