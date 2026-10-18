import { useRef } from 'react'
import { t } from '../../i18n/index.ts'
import { Button } from './Button.tsx'
import type { HeaderRow } from './headerRows.ts'
import { TextField } from './TextField.tsx'

type HeaderRowsEditorProps = {
  rows: HeaderRow[]
  onChange: (rows: HeaderRow[]) => void
  hint: string
  isReplacing: boolean
  onReplace: () => void
  error?: string
}

export function HeaderRowsEditor({ rows, onChange, hint, isReplacing, onReplace, error }: HeaderRowsEditorProps) {
  const nextId = useRef(0)

  const update = (id: number, row: Partial<HeaderRow>) => onChange(rows.map((current) => (current.id === id ? { ...current, ...row } : current)))

  return (
    <fieldset className="flex flex-col gap-2">
      <legend className="text-sm font-medium text-neutral-800">{t('settings.channels.headers.label')}</legend>
      <p className="text-xs text-neutral-500">{hint}</p>
      {!isReplacing ? (
        <div className="flex flex-wrap items-center gap-2 text-sm text-neutral-700">
          <span>{t('settings.channels.headers.saved')}</span>
          <Button variant="secondary" onClick={onReplace}>
            {t('settings.channels.headers.replace')}
          </Button>
        </div>
      ) : (
        <>
          {rows.map((row, index) => (
            <div key={row.id} className="flex items-end gap-2">
              <TextField
                className="flex-1"
                label={t('settings.channels.headers.name')}
                autoComplete="off"
                placeholder="Authorization"
                value={row.name}
                onChange={(event) => update(row.id, { name: event.target.value })}
              />
              <TextField
                className="flex-1"
                label={t('settings.channels.headers.value')}
                type="password"
                autoComplete="off"
                value={row.value}
                onChange={(event) => update(row.id, { value: event.target.value })}
              />
              <Button
                variant="ghost"
                aria-label={t('settings.channels.headers.remove', { position: index + 1 })}
                onClick={() => onChange(rows.filter((current) => current.id !== row.id))}
              >
                ×
              </Button>
            </div>
          ))}
          <div>
            <Button variant="secondary" onClick={() => onChange([...rows, { id: nextId.current++, name: '', value: '' }])}>
              {t('settings.channels.headers.add')}
            </Button>
          </div>
        </>
      )}
      {error && <p className="text-xs font-medium text-status-down">{error}</p>}
    </fieldset>
  )
}
