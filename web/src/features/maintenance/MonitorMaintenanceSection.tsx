import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { listMaintenanceWindows } from '../../api/maintenanceWindows.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import type { MaintenanceWindow } from '../../api/types.ts'
import { Button } from '../../components/ui/Button.tsx'
import { ErrorState } from '../../components/ui/ErrorState.tsx'
import { Skeleton } from '../../components/ui/Skeleton.tsx'
import { t } from '../../i18n/index.ts'
import { MaintenanceWindowDialog } from './MaintenanceWindowDialog.tsx'
import { MaintenanceWindowList } from './MaintenanceWindowList.tsx'

type DialogState = { mode: 'closed' } | { mode: 'create' } | { mode: 'edit'; window: MaintenanceWindow }

export function MonitorMaintenanceSection({ monitorId, canManage }: { monitorId: number; canManage: boolean }) {
  const [page, setPage] = useState(1)
  const windows = useQuery({
    queryKey: queryKeys.monitorMaintenanceWindows(monitorId, page),
    queryFn: () => listMaintenanceWindows({ monitor_id: monitorId, page }),
    placeholderData: keepPreviousData,
  })
  const isPageOutOfRange = windows.data?.data.length === 0 && page > 1

  if (isPageOutOfRange && !windows.isPlaceholderData) {
    setPage(page - 1)
  }
  const [dialog, setDialog] = useState<DialogState>({ mode: 'closed' })

  return (
    <section className="flex flex-col gap-3" aria-labelledby="monitor-maintenance">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 id="monitor-maintenance" className="text-lg font-semibold text-neutral-900">
          {t('monitors.detail.maintenanceHeading')}
        </h2>
        {canManage && (
          <Button variant="secondary" onClick={() => setDialog({ mode: 'create' })}>
            {t('monitors.detail.addMaintenance')}
          </Button>
        )}
      </div>
      {windows.isPending && <Skeleton className="h-16" />}
      {windows.isError && !windows.data && <ErrorState onRetry={() => void windows.refetch()} />}
      {windows.data?.data.length === 0 && page === 1 && <p className="text-neutral-600">{t('monitors.detail.maintenanceEmpty')}</p>}
      {windows.data && windows.data.data.length > 0 && (
        <MaintenanceWindowList windows={windows.data} canManage={canManage} onEdit={(window) => setDialog({ mode: 'edit', window })} onPageChange={setPage} />
      )}
      {dialog.mode !== 'closed' && (
        <MaintenanceWindowDialog
          window={dialog.mode === 'edit' ? dialog.window : undefined}
          initialMonitorIds={[monitorId]}
          onClose={() => setDialog({ mode: 'closed' })}
        />
      )}
    </section>
  )
}
