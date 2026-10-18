import { useMemo } from 'react'
import { SelectField } from '../../components/ui/SelectField.tsx'
import { localeLabel } from '../../i18n/index.ts'
import { timeZoneOptions } from '../../lib/timeZones.ts'

type LocaleSelectProps = {
  label: string
  locales: string[]
  value: string
  onChange: (locale: string) => void
  error?: string
}

export function LocaleSelect({ label, locales, value, onChange, error }: LocaleSelectProps) {
  const options = locales.includes(value) ? locales : [value, ...locales]

  return (
    <SelectField label={label} name="locale" value={value} onChange={(event) => onChange(event.target.value)} error={error}>
      {options.map((locale) => (
        <option key={locale} value={locale}>
          {localeLabel(locale)}
        </option>
      ))}
    </SelectField>
  )
}

type TimeZoneSelectProps = {
  label: string
  value: string
  onChange: (timeZone: string) => void
  hint?: string
  error?: string
}

export function TimeZoneSelect({ label, value, onChange, hint, error }: TimeZoneSelectProps) {
  const options = useMemo(() => timeZoneOptions(value), [value])

  return (
    <SelectField label={label} name="timezone" value={value} onChange={(event) => onChange(event.target.value)} hint={hint} error={error}>
      {options.map((timeZone) => (
        <option key={timeZone} value={timeZone}>
          {timeZone.replaceAll('_', ' ')}
        </option>
      ))}
    </SelectField>
  )
}
