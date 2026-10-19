import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { generalErrorMessage } from '../../api/errors.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import { listStatusPages } from '../../api/statusPages.ts'
import type { StatusPage } from '../../api/types.ts'
import { Badge } from '../../components/ui/Badge.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { Card } from '../../components/ui/Card.tsx'
import { ConfirmDialog } from '../../components/ui/ConfirmDialog.tsx'
import { EmptyState } from '../../components/ui/EmptyState.tsx'
import { ErrorState } from '../../components/ui/ErrorState.tsx'
import { Skeleton } from '../../components/ui/Skeleton.tsx'
import { useToast } from '../../components/ui/toast.ts'
import { t } from '../../i18n/index.ts'
import { canManageResources } from '../../lib/permissions.ts'
import { useAuth } from '../auth/AuthContext.ts'
import { StatusPageDialog } from './StatusPageDialog.tsx'
import { useDeleteStatusPage } from './useStatusPageMutations.ts'

type DialogState = { mode: 'closed' } | { mode: 'create' } | { mode: 'edit'; statusPage: StatusPage }

export function StatusPagesPage() {
  const { user } = useAuth()
  const canManage = canManageResources(user)
  const statusPages = useQuery({ queryKey: queryKeys.statusPages, queryFn: listStatusPages })
  const [dialog, setDialog] = useState<DialogState>({ mode: 'closed' })
  const addButton = canManage ? <Button onClick={() => setDialog({ mode: 'create' })}>{t('statusPages.add')}</Button> : undefined

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="flex max-w-prose flex-col gap-1">
          <h1 className="text-2xl font-semibold text-neutral-900">{t('statusPages.title')}</h1>
          <p className="text-neutral-600">{t('statusPages.description')}</p>
        </div>
        {statusPages.data && statusPages.data.length > 0 && addButton}
      </div>
      <Card className="flex flex-col gap-6 px-5 py-6 sm:px-6">
        {statusPages.isPending && <Skeleton className="h-40" />}
        {statusPages.isError && <ErrorState onRetry={() => void statusPages.refetch()} />}
        {statusPages.data?.length === 0 && <EmptyState title={t('statusPages.empty')} action={addButton} />}
        {statusPages.data && statusPages.data.length > 0 && (
          <ul className="divide-y divide-border rounded-lg border border-border">
            {statusPages.data.map((statusPage) => (
              <StatusPageRow key={statusPage.id} statusPage={statusPage} canManage={canManage} onEdit={() => setDialog({ mode: 'edit', statusPage })} />
            ))}
          </ul>
        )}
      </Card>
      {dialog.mode !== 'closed' && <StatusPageDialog statusPage={dialog.mode === 'edit' ? dialog.statusPage : undefined} onClose={() => setDialog({ mode: 'closed' })} />}
    </div>
  )
}

function StatusPageRow({ statusPage, canManage, onEdit }: { statusPage: StatusPage; canManage: boolean; onEdit: () => void }) {
  const { showToast } = useToast()
  const deleteMutation = useDeleteStatusPage(statusPage.id)
  const [isConfirmingDelete, setIsConfirmingDelete] = useState(false)
  const publicUrl = `${window.location.origin}${statusPage.public_path}`

  const copyLink = () => void navigator.clipboard?.writeText(publicUrl).then(() => showToast(t('statusPages.linkCopied')))

  const confirmDelete = () =>
    deleteMutation.mutate(undefined, {
      onSuccess: () => {
        setIsConfirmingDelete(false)
        showToast(t('statusPages.deleted'))
      },
      onError: (error) => {
        setIsConfirmingDelete(false)
        showToast(generalErrorMessage(error) ?? t('common.genericError'), 'error')
      },
    })

  return (
    <li className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
      <div className="flex min-w-0 flex-col gap-1">
        <div className="flex flex-wrap items-center gap-2">
          <span className="truncate font-semibold text-neutral-900">{statusPage.name}</span>
          {statusPage.is_published ? (
            <Badge className="bg-status-up-soft text-status-up">{t('statusPages.published')}</Badge>
          ) : (
            <Badge className="bg-neutral-100 text-neutral-600">{t('statusPages.draft')}</Badge>
          )}
        </div>
        <p className="text-xs text-neutral-500">
          {statusPage.public_path} · {t('statusPages.monitorsCount', { count: statusPage.monitors.length + statusPage.hidden_monitors_count })}
        </p>
      </div>
      <div className="flex flex-wrap gap-2">
        {statusPage.is_published && (
          <>
            <a href={statusPage.public_path} target="_blank" rel="noopener noreferrer" className="inline-flex min-h-10 items-center px-3 text-sm font-medium text-primary-700 hover:underline">
              {t('statusPages.open')}
            </a>
            <Button variant="secondary" onClick={copyLink}>
              {t('statusPages.copyLink')}
            </Button>
          </>
        )}
        {canManage && (
          <>
            <Button variant="secondary" onClick={onEdit} aria-label={t('statusPages.edit', { name: statusPage.name })}>
              {t('monitors.detail.edit')}
            </Button>
            <Button variant="ghost" className="text-status-down" onClick={() => setIsConfirmingDelete(true)} aria-label={t('statusPages.delete', { name: statusPage.name })}>
              {t('monitors.detail.delete')}
            </Button>
          </>
        )}
      </div>
      <ConfirmDialog
        isOpen={isConfirmingDelete}
        title={t('statusPages.deleteTitle', { name: statusPage.name })}
        description={t('statusPages.deleteDescription')}
        confirmLabel={t('statusPages.deleteConfirm')}
        pendingLabel={t('statusPages.deleting')}
        isPending={deleteMutation.isPending}
        onConfirm={confirmDelete}
        onClose={() => setIsConfirmingDelete(false)}
      />
    </li>
  )
}
