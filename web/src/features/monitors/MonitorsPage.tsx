import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { listMonitors, type MonitorStatusFilter } from '../../api/monitors.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import { Button } from '../../components/ui/Button.tsx'
import { EmptyState } from '../../components/ui/EmptyState.tsx'
import { ErrorState } from '../../components/ui/ErrorState.tsx'
import { Skeleton } from '../../components/ui/Skeleton.tsx'
import { t } from '../../i18n/index.ts'
import { canManageResources } from '../../lib/permissions.ts'
import { useAuth } from '../auth/AuthContext.ts'
import { MonitorFormDialog } from './MonitorFormDialog.tsx'
import { usePollingInterval } from './realtimeConnection.ts'
import { MonitorList } from './MonitorList.tsx'

const STATUS_FILTERS: MonitorStatusFilter[] = ['up', 'down', 'pending', 'paused']

export function MonitorsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const navigate = useNavigate()
  const [isFormOpen, setIsFormOpen] = useState(false)
  const { user } = useAuth()
  const canManage = canManageResources(user)
  const page = Math.max(1, Number(searchParams.get('page') ?? 1) || 1)
  const statusParam = searchParams.get('status')
  const status = STATUS_FILTERS.find((filter) => filter === statusParam)

  const refetchInterval = usePollingInterval(60_000)
  const monitors = useQuery({
    queryKey: queryKeys.monitorList(page, status),
    queryFn: () => listMonitors({ page, status }),
    placeholderData: keepPreviousData,
    refetchInterval,
  })

  const updateParams = (next: { page?: number; status?: string }) => {
    const params = new URLSearchParams()
    const nextStatus = 'status' in next ? next.status : status

    if (nextStatus) {
      params.set('status', nextStatus)
    }

    if (next.page && next.page > 1) {
      params.set('page', String(next.page))
    }

    setSearchParams(params)
  }

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-2xl font-semibold text-neutral-900">{t('monitors.title')}</h1>
        {canManage && <Button onClick={() => setIsFormOpen(true)}>{t('monitors.add')}</Button>}
      </div>

      <div className="flex items-center gap-2">
        <label htmlFor="status-filter" className="text-sm font-medium text-neutral-700">
          {t('monitors.filter.label')}
        </label>
        <select
          id="status-filter"
          value={status ?? ''}
          onChange={(event) => updateParams({ status: event.target.value || undefined, page: 1 })}
          className="min-h-10 rounded-md border border-neutral-300 bg-surface px-3 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200"
        >
          <option value="">{t('monitors.filter.all')}</option>
          {STATUS_FILTERS.map((filter) => (
            <option key={filter} value={filter}>
              {t(`status.${filter}`)}
            </option>
          ))}
        </select>
      </div>

      {monitors.isPending && <Skeleton className="h-64" />}
      {monitors.isError && <ErrorState onRetry={() => void monitors.refetch()} />}
      {monitors.data && monitors.data.data.length === 0 && page > 1 && (
        <div className="flex flex-col items-start gap-3">
          <p className="text-neutral-600">{t('monitors.pageOutOfRange')}</p>
          <Button variant="secondary" onClick={() => updateParams({ page: 1 })}>
            {t('monitors.firstPage')}
          </Button>
        </div>
      )}
      {monitors.data && monitors.data.data.length === 0 && page === 1 && status === undefined && (
        <EmptyState
          title={t('dashboard.empty.title')}
          description={t('dashboard.empty.description')}
          action={canManage ? <Button onClick={() => setIsFormOpen(true)}>{t('dashboard.empty.action')}</Button> : undefined}
        />
      )}
      {monitors.data && monitors.data.data.length === 0 && page === 1 && status !== undefined && <p className="text-neutral-600">{t('monitors.noMatches')}</p>}
      {monitors.data && monitors.data.data.length > 0 && (
        <>
          <MonitorList monitors={monitors.data.data} />
          {monitors.data.meta.last_page > 1 && (
            <nav className="flex items-center justify-between gap-3" aria-label={t('monitors.pagination.page', { page, total: monitors.data.meta.last_page })}>
              <Button variant="secondary" disabled={page <= 1} onClick={() => updateParams({ page: page - 1 })}>
                {t('monitors.pagination.previous')}
              </Button>
              <span className="text-neutral-600">{t('monitors.pagination.page', { page, total: monitors.data.meta.last_page })}</span>
              <Button variant="secondary" disabled={page >= monitors.data.meta.last_page} onClick={() => updateParams({ page: page + 1 })}>
                {t('monitors.pagination.next')}
              </Button>
            </nav>
          )}
        </>
      )}

      <MonitorFormDialog isOpen={isFormOpen} onClose={() => setIsFormOpen(false)} onSaved={(monitor) => navigate(`/monitors/${monitor.id}`)} />
    </div>
  )
}
