import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { Navigate, useSearchParams } from 'react-router'
import { listAuditLog } from '../../api/auditLog.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import type { AuditLogEntry } from '../../api/types.ts'
import { Button } from '../../components/ui/Button.tsx'
import { Card } from '../../components/ui/Card.tsx'
import { ErrorState } from '../../components/ui/ErrorState.tsx'
import { SelectField } from '../../components/ui/SelectField.tsx'
import { Skeleton } from '../../components/ui/Skeleton.tsx'
import { t } from '../../i18n/index.ts'
import { formatDateTime } from '../../lib/format.ts'
import { isAdmin } from '../../lib/permissions.ts'
import { useAuth } from '../auth/AuthContext.ts'
import { AUDIT_SUBJECT_TYPES, describeAction, isAuditSubjectType, subjectTypeLabel } from './auditActions.ts'

export function AuditLogPage() {
  const { user } = useAuth()
  const [searchParams, setSearchParams] = useSearchParams()
  const page = Math.max(1, Number(searchParams.get('page') ?? 1) || 1)
  const typeParam = searchParams.get('type')
  const subjectType = isAuditSubjectType(typeParam) ? typeParam : undefined

  const entries = useQuery({
    queryKey: queryKeys.auditLog(page, subjectType),
    queryFn: () => listAuditLog({ page, subject_type: subjectType }),
    placeholderData: keepPreviousData,
    enabled: isAdmin(user),
  })

  if (!isAdmin(user)) {
    return <Navigate to="/dashboard" replace />
  }

  const updateParams = (next: { page?: number; type?: string }) => {
    const params = new URLSearchParams()
    const nextType = 'type' in next ? next.type : subjectType

    if (nextType) {
      params.set('type', nextType)
    }

    if (next.page && next.page > 1) {
      params.set('page', String(next.page))
    }

    setSearchParams(params)
  }

  const lastPage = entries.data?.meta.last_page ?? 1

  return (
    <section aria-labelledby="audit-log-heading" className="flex flex-col gap-6">
      <div className="flex max-w-prose flex-col gap-1">
        <h1 id="audit-log-heading" className="text-2xl font-semibold text-neutral-900">{t('auditLog.title')}</h1>
        <p className="text-neutral-600">{t('auditLog.description')}</p>
      </div>

      <SelectField
        label={t('auditLog.filter')}
        value={subjectType ?? ''}
        onChange={(event) => updateParams({ type: event.target.value || undefined, page: 1 })}
        className="flex-row! items-center gap-2! self-start"
      >
        <option value="">{t('auditLog.allSubjects')}</option>
        {AUDIT_SUBJECT_TYPES.map((type) => (
          <option key={type} value={type}>
            {subjectTypeLabel(type)}
          </option>
        ))}
      </SelectField>

      {entries.isPending && <Skeleton className="h-64" />}
      {entries.isError && !entries.data && <ErrorState onRetry={() => void entries.refetch()} />}
      {entries.data && entries.data.data.length === 0 && <p className="text-neutral-600">{t('auditLog.empty')}</p>}
      {entries.data && entries.data.data.length > 0 && (
        <Card>
          <ul className="divide-y divide-border">
            {entries.data.data.map((entry) => (
              <AuditLogRow key={entry.id} entry={entry} />
            ))}
          </ul>
        </Card>
      )}
      {lastPage > 1 && (
        <nav className="flex items-center justify-between gap-3" aria-label={t('monitors.pagination.page', { page, total: lastPage })}>
          <Button variant="secondary" disabled={page <= 1} onClick={() => updateParams({ page: page - 1 })}>
            {t('monitors.pagination.previous')}
          </Button>
          <span className="text-neutral-600">{t('monitors.pagination.page', { page, total: lastPage })}</span>
          <Button variant="secondary" disabled={page >= lastPage} onClick={() => updateParams({ page: page + 1 })}>
            {t('monitors.pagination.next')}
          </Button>
        </nav>
      )}
    </section>
  )
}

function AuditLogRow({ entry }: { entry: AuditLogEntry }) {
  const fields = entry.metadata.fields ?? []
  const actor = entry.actor?.name ?? (entry.metadata.email ? entry.metadata.email : t('auditLog.system'))
  const isSelfAction = entry.subject?.type === 'user' && entry.actor?.id !== null && entry.subject.id === entry.actor?.id
  const subjectLabel = isSelfAction && !entry.action.startsWith('user.') ? null : entry.subject?.label

  return (
    <li className="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-6">
      <div className="flex min-w-0 flex-col gap-0.5">
        <p className="text-neutral-900">
          <span className="font-semibold">{actor}</span> <span className="text-neutral-700">{describeAction(entry)}</span>
          {subjectLabel && <span className="font-medium text-neutral-900"> {subjectLabel}</span>}
        </p>
        {fields.length > 0 && <p className="text-xs text-neutral-500">{t('auditLog.changedFields', { fields: fields.join(', ') })}</p>}
      </div>
      <div className="flex shrink-0 flex-col text-xs text-neutral-500 sm:items-end">
        <time dateTime={entry.created_at}>{formatDateTime(entry.created_at)}</time>
        {entry.ip_address && <span>{entry.ip_address}</span>}
      </div>
    </li>
  )
}
