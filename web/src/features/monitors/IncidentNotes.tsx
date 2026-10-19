import { useQuery } from '@tanstack/react-query'
import { useId, useState, type FormEvent } from 'react'
import { fieldErrors, generalErrorMessage } from '../../api/errors.ts'
import { listIncidentNotes } from '../../api/incidents.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import { Button } from '../../components/ui/Button.tsx'
import { inputClassName } from '../../components/ui/inputClassName.ts'
import { RelativeTime } from '../../components/ui/RelativeTime.tsx'
import { Skeleton } from '../../components/ui/Skeleton.tsx'
import { t } from '../../i18n/index.ts'
import { useCreateIncidentNote, useDeleteIncidentNote } from './useIncidentMutations.ts'

export function IncidentNotes({ incidentId }: { incidentId: number }) {
  const fieldId = useId()
  const [body, setBody] = useState('')
  const notes = useQuery({ queryKey: queryKeys.incidentNotes(incidentId), queryFn: () => listIncidentNotes(incidentId) })
  const createMutation = useCreateIncidentNote(incidentId)
  const deleteMutation = useDeleteIncidentNote(incidentId)
  const error = fieldErrors(createMutation.error).body ?? (createMutation.isError ? generalErrorMessage(createMutation.error) : null)

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault()

    if (body.trim() === '' || createMutation.isPending) {
      return
    }

    createMutation.mutate(body.trim(), { onSuccess: () => setBody('') })
  }

  return (
    <div className="flex flex-col gap-3 border-t border-border pt-3">
      {notes.isPending && <Skeleton className="h-10" />}
      {notes.data?.length === 0 && <p className="text-xs text-neutral-500">{t('monitors.incidents.notes.empty')}</p>}
      {notes.data && notes.data.length > 0 && (
        <ul className="flex flex-col gap-2">
          {notes.data.map((note) => (
            <li key={note.id} className="flex items-start justify-between gap-3 rounded-md bg-neutral-50 px-3 py-2 text-sm">
              <div className="min-w-0">
                <p className="whitespace-pre-wrap break-words text-neutral-800">{note.body}</p>
                <p className="text-xs text-neutral-500">
                  {note.user?.name ?? t('monitors.incidents.notes.deletedUser')} · <RelativeTime value={note.created_at} />
                </p>
              </div>
              {note.can_delete && (
                <Button
                  variant="ghost"
                  aria-label={t('monitors.incidents.notes.delete')}
                  onClick={() => deleteMutation.mutate(note.id)}
                  disabled={deleteMutation.isPending}
                >
                  ×
                </Button>
              )}
            </li>
          ))}
        </ul>
      )}
      <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-2">
        <label htmlFor={fieldId} className="text-sm font-medium text-neutral-800">
          {t('monitors.incidents.notes.label')}
        </label>
        <textarea
          id={fieldId}
          rows={2}
          maxLength={2000}
          placeholder={t('monitors.incidents.notes.placeholder')}
          value={body}
          onChange={(event) => setBody(event.target.value)}
          aria-invalid={error ? true : undefined}
          aria-describedby={error ? `${fieldId}-error` : undefined}
          className={`${inputClassName(error ?? undefined)} py-2`}
        />
        {error && (
          <p id={`${fieldId}-error`} className="text-xs font-medium text-status-down">
            {error}
          </p>
        )}
        <div>
          <Button type="submit" variant="secondary" isLoading={createMutation.isPending} loadingLabel={t('monitors.incidents.notes.submitting')}>
            {t('monitors.incidents.notes.submit')}
          </Button>
        </div>
      </form>
    </div>
  )
}
