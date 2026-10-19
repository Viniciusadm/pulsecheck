import { useState } from 'react'
import { Link } from 'react-router'
import { generalErrorMessage } from '../../api/errors.ts'
import type { MaintenanceWindow } from '../../api/types.ts'
import { Badge } from '../../components/ui/Badge.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { ConfirmDialog } from '../../components/ui/ConfirmDialog.tsx'
import { useToast } from '../../components/ui/toast.ts'
import { t } from '../../i18n/index.ts'
import { formatDateTime } from '../../lib/format.ts'
import { scheduleSummary } from './maintenanceSummary.ts'
import { useDeleteMaintenanceWindow } from './useMaintenanceWindowMutations.ts'

type MaintenanceWindowListProps = {
  windows: MaintenanceWindow[]
  canManage: boolean
  onEdit: (window: MaintenanceWindow) => void
}

export function MaintenanceWindowList({ windows, canManage, onEdit }: MaintenanceWindowListProps) {
  return (
    <ul className="divide-y divide-border rounded-lg border border-border bg-surface">
      {windows.map((window) => (
        <MaintenanceWindowRow key={window.id} window={window} canManage={canManage} onEdit={() => onEdit(window)} />
      ))}
    </ul>
  )
}

function statusBadge(window: MaintenanceWindow) {
  if (window.active_until) {
    return <Badge className="bg-status-maintenance-soft text-status-maintenance">{t('maintenance.active', { time: formatDateTime(window.active_until) })}</Badge>
  }

  if (window.next_starts_at) {
    return <Badge className="bg-neutral-100 text-neutral-700">{t('maintenance.next', { time: formatDateTime(window.next_starts_at) })}</Badge>
  }

  return <Badge className="bg-neutral-100 text-neutral-500">{t('maintenance.finished')}</Badge>
}

function MaintenanceWindowRow({ window, canManage, onEdit }: { window: MaintenanceWindow; canManage: boolean; onEdit: () => void }) {
  const { showToast } = useToast()
  const deleteMutation = useDeleteMaintenanceWindow(window.id)
  const [isConfirmingDelete, setIsConfirmingDelete] = useState(false)

  const confirmDelete = () =>
    deleteMutation.mutate(undefined, {
      onSuccess: () => {
        setIsConfirmingDelete(false)
        showToast(t('maintenance.deleted'))
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
          <span className="truncate font-semibold text-neutral-900">{window.name}</span>
          {statusBadge(window)}
        </div>
        <p className="text-sm text-neutral-600">{scheduleSummary(window)}</p>
        <p className="flex flex-wrap gap-x-2 text-xs text-neutral-500">
          {window.monitors.map((monitor) => (
            <Link key={monitor.id} to={`/monitors/${monitor.id}`} className="hover:text-primary-700 hover:underline">
              {monitor.name}
            </Link>
          ))}
          {window.team_ids.length > 0 && <span>{t('maintenance.targets', { monitors: window.monitors.length, teams: window.team_ids.length })}</span>}
        </p>
      </div>
      {canManage && (
        <div className="flex flex-wrap gap-2">
          <Button variant="secondary" onClick={onEdit} aria-label={t('maintenance.edit', { name: window.name })}>
            {t('monitors.detail.edit')}
          </Button>
          <Button variant="ghost" className="text-status-down" onClick={() => setIsConfirmingDelete(true)} aria-label={t('maintenance.delete', { name: window.name })}>
            {t('monitors.detail.delete')}
          </Button>
        </div>
      )}
      <ConfirmDialog
        isOpen={isConfirmingDelete}
        title={t('maintenance.deleteTitle', { name: window.name })}
        description={t('maintenance.deleteDescription')}
        confirmLabel={t('maintenance.deleteConfirm')}
        pendingLabel={t('maintenance.deleting')}
        isPending={deleteMutation.isPending}
        onConfirm={confirmDelete}
        onClose={() => setIsConfirmingDelete(false)}
      />
    </li>
  )
}
