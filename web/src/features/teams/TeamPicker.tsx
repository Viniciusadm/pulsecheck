import { useId } from 'react'
import type { Team } from '../../api/types.ts'
import { t } from '../../i18n/index.ts'

type TeamPickerProps = {
  teams?: Team[]
  selectedIds: number[]
  onChange: (ids: number[]) => void
  legend?: string
  hint?: string
  isDisabled?: boolean
  error?: string
}

export function TeamPicker({
  teams,
  selectedIds,
  onChange,
  legend = t('settings.teams.picker.label'),
  hint = t('settings.teams.picker.hint'),
  isDisabled = false,
  error,
}: TeamPickerProps) {
  const hintId = useId()
  const errorId = useId()

  if (!teams) {
    return null
  }

  const toggle = (id: number, isChecked: boolean) =>
    onChange(isChecked ? [...selectedIds, id] : selectedIds.filter((current) => current !== id))

  return (
    <fieldset
      className="flex flex-col gap-2"
      disabled={isDisabled}
      aria-describedby={[hint ? hintId : undefined, error ? errorId : undefined].filter(Boolean).join(' ') || undefined}
    >
      <legend className="text-sm font-medium text-neutral-800">{legend}</legend>
      {hint && (
        <p id={hintId} className="text-xs text-neutral-500">
          {hint}
        </p>
      )}
      {teams.length === 0 ? (
        <p className="text-sm text-neutral-600">{t('settings.teams.picker.empty')}</p>
      ) : (
        <div className="grid gap-2 sm:grid-cols-2">
          {teams.map((team) => (
            <label key={team.id} className={`inline-flex items-center gap-2 text-sm ${isDisabled ? 'text-neutral-400' : 'text-neutral-800'}`}>
              <input
                type="checkbox"
                name="team_ids"
                value={team.id}
                checked={isDisabled || selectedIds.includes(team.id)}
                onChange={(event) => toggle(team.id, event.target.checked)}
                aria-invalid={error ? true : undefined}
                className="size-4 accent-primary-600"
              />
              <span className="font-medium">{team.name}</span>
            </label>
          ))}
        </div>
      )}
      {error && (
        <p id={errorId} className="text-xs font-medium text-status-down">
          {error}
        </p>
      )}
    </fieldset>
  )
}
