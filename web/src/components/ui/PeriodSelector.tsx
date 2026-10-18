import type { StatsPeriod } from '../../api/types.ts'

const PERIODS: StatsPeriod[] = ['24h', '7d', '30d']

type PeriodSelectorProps = {
  value: StatsPeriod
  label: string
  optionLabel: (period: StatsPeriod) => string
  onChange: (period: StatsPeriod) => void
}

export function PeriodSelector({ value, label, optionLabel, onChange }: PeriodSelectorProps) {
  return (
    <div role="group" aria-label={label} className="inline-flex rounded-md border border-neutral-300 bg-surface p-0.5">
      {PERIODS.map((option) => (
        <button
          key={option}
          type="button"
          aria-pressed={value === option}
          onClick={() => onChange(option)}
          className={`rounded px-3 py-1.5 text-sm font-medium ${value === option ? 'bg-primary-700 text-white' : 'text-neutral-700 hover:bg-neutral-100'}`}
        >
          {optionLabel(option)}
        </button>
      ))}
    </div>
  )
}
