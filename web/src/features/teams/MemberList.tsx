import { useState } from 'react'
import { Button } from '../../components/ui/Button.tsx'
import { SelectField } from '../../components/ui/SelectField.tsx'
import { t } from '../../i18n/index.ts'

type MemberItem = {
  id: number
  name: string
  detail?: string
}

type MemberListProps = {
  items: MemberItem[]
  selectedIds: number[]
  onChange: (ids: number[]) => void
  addLabel: string
  emptyLabel: string
  noneLeftLabel: string
  error?: string
}

export function MemberList({ items, selectedIds, onChange, addLabel, emptyLabel, noneLeftLabel, error }: MemberListProps) {
  const [idToAdd, setIdToAdd] = useState('')
  const selected = items.filter((item) => selectedIds.includes(item.id))
  const available = items.filter((item) => !selectedIds.includes(item.id))

  const add = () => {
    if (idToAdd !== '') {
      onChange([...selectedIds, Number(idToAdd)])
      setIdToAdd('')
    }
  }

  return (
    <div className="flex flex-col gap-3">
      {selected.length === 0 ? (
        <p className="text-sm text-neutral-600">{emptyLabel}</p>
      ) : (
        <ul className="divide-y divide-border rounded-lg border border-border">
          {selected.map((item) => (
            <li key={item.id} className="flex items-center justify-between gap-3 px-4 py-2.5">
              <div className="flex min-w-0 flex-col">
                <span className="truncate font-medium text-neutral-900">{item.name}</span>
                {item.detail && <span className="truncate text-xs text-neutral-500">{item.detail}</span>}
              </div>
              <Button
                variant="ghost"
                className="text-status-down"
                aria-label={t('settings.teams.form.remove', { name: item.name })}
                onClick={() => onChange(selectedIds.filter((id) => id !== item.id))}
              >
                {t('settings.teams.form.removeShort')}
              </Button>
            </li>
          ))}
        </ul>
      )}
      {available.length > 0 ? (
        <div className="flex items-end gap-2">
          <SelectField className="flex-1" label={addLabel} value={idToAdd} onChange={(event) => setIdToAdd(event.target.value)}>
            <option value="">—</option>
            {available.map((item) => (
              <option key={item.id} value={item.id}>
                {item.detail ? `${item.name} (${item.detail})` : item.name}
              </option>
            ))}
          </SelectField>
          <Button variant="secondary" onClick={add} disabled={idToAdd === ''}>
            {t('settings.teams.form.add')}
          </Button>
        </div>
      ) : (
        items.length > 0 && <p className="text-xs text-neutral-500">{noneLeftLabel}</p>
      )}
      {error && <p className="text-xs font-medium text-status-down">{error}</p>}
    </div>
  )
}
