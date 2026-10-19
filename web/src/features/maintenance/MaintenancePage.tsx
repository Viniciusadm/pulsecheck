import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { listMaintenanceWindows } from '../../api/maintenanceWindows.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import type { MaintenanceWindow } from '../../api/types.ts'
import { Button } from '../../components/ui/Button.tsx'
import { Card } from '../../components/ui/Card.tsx'
import { EmptyState } from '../../components/ui/EmptyState.tsx'
import { ErrorState } from '../../components/ui/ErrorState.tsx'
import { Skeleton } from '../../components/ui/Skeleton.tsx'
import { t } from '../../i18n/index.ts'
import { canManageResources } from '../../lib/permissions.ts'
import { useAuth } from '../auth/AuthContext.ts'
import { MaintenanceWindowDialog } from './MaintenanceWindowDialog.tsx'
import { MaintenanceWindowList } from './MaintenanceWindowList.tsx'

type DialogState = { mode: 'closed' } | { mode: 'create' } | { mode: 'edit'; window: MaintenanceWindow }

export function MaintenancePage() {
  const { user } = useAuth()
  const canManage = canManageResources(user)
  const windows = useQuery({ queryKey: queryKeys.maintenanceWindows, queryFn: () => listMaintenanceWindows() })
  const [dialog, setDialog] = useState<DialogState>({ mode: 'closed' })
  const addButton = canManage ? <Button onClick={() => setDialog({ mode: 'create' })}>{t('maintenance.add')}</Button> : undefined

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="flex max-w-prose flex-col gap-1">
          <h1 className="text-2xl font-semibold text-neutral-900">{t('maintenance.title')}</h1>
          <p className="text-neutral-600">{t('maintenance.description')}</p>
        </div>
        {windows.data && windows.data.data.length > 0 && addButton}
      </div>
      <Card className="flex flex-col gap-6 px-5 py-6 sm:px-6">
        {windows.isPending && <Skeleton className="h-40" />}
        {windows.isError && <ErrorState onRetry={() => void windows.refetch()} />}
        {windows.data?.data.length === 0 && <EmptyState title={t('maintenance.empty')} action={addButton} />}
        {windows.data && windows.data.data.length > 0 && (
          <MaintenanceWindowList windows={windows.data.data} canManage={canManage} onEdit={(window) => setDialog({ mode: 'edit', window })} />
        )}
      </Card>
      {dialog.mode !== 'closed' && (
        <MaintenanceWindowDialog window={dialog.mode === 'edit' ? dialog.window : undefined} onClose={() => setDialog({ mode: 'closed' })} />
      )}
    </div>
  )
}
