import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { generalErrorMessage } from '../../api/errors.ts'
import { bulkMonitorAction, listMonitors, type BulkMonitorAction, type MonitorStatusFilter } from '../../api/monitors.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import { Button } from '../../components/ui/Button.tsx'
import { ConfirmDialog } from '../../components/ui/ConfirmDialog.tsx'
import { EmptyState } from '../../components/ui/EmptyState.tsx'
import { ErrorState } from '../../components/ui/ErrorState.tsx'
import { inputClassName } from '../../components/ui/inputClassName.ts'
import { SelectField } from '../../components/ui/SelectField.tsx'
import { Skeleton } from '../../components/ui/Skeleton.tsx'
import { useToast } from '../../components/ui/toast.ts'
import { t } from '../../i18n/index.ts'
import { canManageResources } from '../../lib/permissions.ts'
import { useAuth } from '../auth/AuthContext.ts'
import { usePollingInterval } from './realtimeConnection.ts'
import { MonitorList } from './MonitorList.tsx'

const STATUS_FILTERS: MonitorStatusFilter[] = ['up', 'degraded', 'down', 'pending', 'maintenance', 'paused']

const SEARCH_DEBOUNCE_MS = 300

export function MonitorsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { showToast } = useToast()
  const { user } = useAuth()
  const canManage = canManageResources(user)
  const page = Math.max(1, Number(searchParams.get('page') ?? 1) || 1)
  const statusParam = searchParams.get('status')
  const status = STATUS_FILTERS.find((filter) => filter === statusParam)
  const search = (searchParams.get('q') ?? '').trim()
  const [searchInput, setSearchInput] = useState(search)
  const [selectedIds, setSelectedIds] = useState<number[]>([])
  const [isConfirmingDelete, setIsConfirmingDelete] = useState(false)

  const refetchInterval = usePollingInterval(60_000)
  const monitors = useQuery({
    queryKey: queryKeys.monitorList(page, status, search),
    queryFn: () => listMonitors({ page, status, ...(search ? { search } : {}) }),
    placeholderData: keepPreviousData,
    refetchInterval,
  })

  const updateParams = (next: { page?: number; status?: string; q?: string }) => {
    const params = new URLSearchParams()
    const nextStatus = 'status' in next ? next.status : status
    const nextSearch = 'q' in next ? next.q : search

    if (nextStatus) {
      params.set('status', nextStatus)
    }

    if (nextSearch) {
      params.set('q', nextSearch)
    }

    if (next.page && next.page > 1) {
      params.set('page', String(next.page))
    }

    setSelectedIds([])
    setSearchParams(params)
  }

  useEffect(() => {
    const trimmed = searchInput.trim()

    if (trimmed === search) {
      return
    }

    const timer = setTimeout(() => {
      const params = new URLSearchParams(searchParams)
      params.delete('page')

      if (trimmed) {
        params.set('q', trimmed)
      } else {
        params.delete('q')
      }

      setSelectedIds([])
      setSearchParams(params)
    }, SEARCH_DEBOUNCE_MS)

    return () => clearTimeout(timer)
  }, [searchInput, search, searchParams, setSearchParams])

  const bulkMutation = useMutation({
    mutationFn: ({ action, ids }: { action: BulkMonitorAction; ids: number[] }) => bulkMonitorAction(action, ids),
    onSuccess: (affected, { action }) => {
      setSelectedIds([])
      setIsConfirmingDelete(false)
      void queryClient.invalidateQueries({ queryKey: queryKeys.monitors })
      void queryClient.invalidateQueries({ queryKey: ['dashboard'] })
      showToast(t(action === 'delete' ? 'monitors.bulk.deleted' : 'monitors.bulk.done', { count: affected }))
    },
    onError: (error) => {
      setIsConfirmingDelete(false)
      showToast(generalErrorMessage(error) ?? t('common.genericError'), 'error')
    },
  })

  const visibleIds = monitors.data?.data.map((monitor) => monitor.id) ?? []
  const selectedOnPage = selectedIds.filter((id) => visibleIds.includes(id))
  const hasFilters = status !== undefined || search !== ''

  const toggleSelected = (id: number) =>
    setSelectedIds((current) => (current.includes(id) ? current.filter((selected) => selected !== id) : [...current, id]))

  const toggleAll = () => setSelectedIds(selectedOnPage.length === visibleIds.length ? [] : visibleIds)

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="page-title">{t('monitors.title')}</h1>
        {canManage && <Button onClick={() => navigate('/monitors/new')}>{t('monitors.add')}</Button>}
      </div>

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <input
          type="search"
          aria-label={t('monitors.search.label')}
          placeholder={t('monitors.search.placeholder')}
          value={searchInput}
          maxLength={100}
          onChange={(event) => setSearchInput(event.target.value)}
          className={`${inputClassName()} w-full sm:max-w-xs`}
        />
        <SelectField
          id="status-filter"
          label={t('monitors.filter.label')}
          value={status ?? ''}
          onChange={(event) => updateParams({ status: event.target.value || undefined, page: 1 })}
          className="flex-row! items-center gap-2! self-start sm:self-auto"
        >
          <option value="">{t('monitors.filter.all')}</option>
          {STATUS_FILTERS.map((filter) => (
            <option key={filter} value={filter}>
              {t(`status.${filter}`)}
            </option>
          ))}
        </SelectField>
      </div>

      {canManage && selectedOnPage.length > 0 && (
        <div role="region" aria-label={t('monitors.bulk.selected', { count: selectedOnPage.length })} className="sticky top-16 z-10 flex flex-wrap items-center gap-2 rounded-lg border border-primary-200 bg-primary-50 px-4 py-2 lg:top-4">
          <span className="mr-auto font-medium text-primary-900">{t('monitors.bulk.selected', { count: selectedOnPage.length })}</span>
          <Button variant="secondary" disabled={bulkMutation.isPending} onClick={() => bulkMutation.mutate({ action: 'pause', ids: selectedOnPage })}>
            {t('monitors.bulk.pause')}
          </Button>
          <Button variant="secondary" disabled={bulkMutation.isPending} onClick={() => bulkMutation.mutate({ action: 'resume', ids: selectedOnPage })}>
            {t('monitors.bulk.resume')}
          </Button>
          <Button variant="danger" disabled={bulkMutation.isPending} onClick={() => setIsConfirmingDelete(true)}>
            {t('monitors.bulk.delete')}
          </Button>
          <Button variant="ghost" onClick={() => setSelectedIds([])}>
            {t('monitors.bulk.clear')}
          </Button>
        </div>
      )}

      {monitors.isPending && <Skeleton className="h-64" />}
      {monitors.isError && !monitors.data && <ErrorState onRetry={() => void monitors.refetch()} />}
      {monitors.data && monitors.data.data.length === 0 && page > 1 && (
        <div className="flex flex-col items-start gap-3">
          <p className="text-neutral-600">{t('monitors.pageOutOfRange')}</p>
          <Button variant="secondary" onClick={() => updateParams({ page: 1 })}>
            {t('monitors.firstPage')}
          </Button>
        </div>
      )}
      {monitors.data && monitors.data.data.length === 0 && page === 1 && !hasFilters && (
        <EmptyState
          title={t('dashboard.empty.title')}
          description={t('dashboard.empty.description')}
          action={canManage ? <Button onClick={() => navigate('/monitors/new')}>{t('dashboard.empty.action')}</Button> : undefined}
        />
      )}
      {monitors.data && monitors.data.data.length === 0 && page === 1 && hasFilters && (
        <p className="text-neutral-600">{search ? t('monitors.noSearchMatches', { search }) : t('monitors.noMatches')}</p>
      )}
      {monitors.data && monitors.data.data.length > 0 && (
        <>
          <MonitorList
            monitors={monitors.data.data}
            selection={canManage ? { selectedIds: selectedOnPage, onToggle: toggleSelected, onToggleAll: toggleAll } : undefined}
          />
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

      <ConfirmDialog
        isOpen={isConfirmingDelete}
        title={t('monitors.bulk.deleteTitle', { count: selectedOnPage.length })}
        description={t('monitors.bulk.deleteDescription')}
        confirmLabel={t('monitors.bulk.delete')}
        pendingLabel={t('monitors.bulk.deleting')}
        isPending={bulkMutation.isPending}
        onConfirm={() => bulkMutation.mutate({ action: 'delete', ids: selectedOnPage })}
        onClose={() => setIsConfirmingDelete(false)}
      />
    </div>
  )
}
