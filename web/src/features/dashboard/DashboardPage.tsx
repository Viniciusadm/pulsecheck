import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { lazy, Suspense, useState, type ReactNode } from 'react'
import { useNavigate } from 'react-router'
import { getDashboardLayout, getDashboardSummary, resetDashboardLayout, updateDashboardLayout } from '../../api/dashboard.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import type { DashboardLayout, DashboardWidget, StatsPeriod } from '../../api/types.ts'
import { Button } from '../../components/ui/Button.tsx'
import { ConfirmDialog } from '../../components/ui/ConfirmDialog.tsx'
import { EmptyState } from '../../components/ui/EmptyState.tsx'
import { ErrorState } from '../../components/ui/ErrorState.tsx'
import { PlusIcon } from '../../components/ui/icons.tsx'
import { Skeleton } from '../../components/ui/Skeleton.tsx'
import { useToast } from '../../components/ui/toast.ts'
import { t } from '../../i18n/index.ts'
import { canManageResources } from '../../lib/permissions.ts'
import { useAuth } from '../auth/AuthContext.ts'
import { MonitorFormDialog } from '../monitors/MonitorFormDialog.tsx'
import { AddWidgetDialog } from './AddWidgetDialog.tsx'
import { addWidget, MAX_WIDGETS, removeWidget } from './layout.ts'
import { useRefreshInterval } from './widgets/refresh.ts'

const PERIOD_MUTATION_KEY = ['dashboard-layout', 'period']

const WidgetGrid = lazy(() => import('./WidgetGrid.tsx').then((module) => ({ default: module.WidgetGrid })))

const PERIODS: StatsPeriod[] = ['24h', '7d', '30d']

export function DashboardPage() {
  const refetchInterval = useRefreshInterval()
  const [isFormOpen, setIsFormOpen] = useState(false)
  const { user } = useAuth()
  const canManage = canManageResources(user)
  const navigate = useNavigate()
  const summary = useQuery({ queryKey: queryKeys.summary, queryFn: getDashboardSummary, refetchInterval })
  const layout = useQuery({ queryKey: queryKeys.dashboardLayout, queryFn: getDashboardLayout })

  const title = <h1 className="text-2xl font-semibold text-neutral-900">{t('dashboard.title')}</h1>

  const formDialog = (
    <MonitorFormDialog isOpen={isFormOpen} onClose={() => setIsFormOpen(false)} onSaved={(monitor) => navigate(`/monitors/${monitor.id}`)} />
  )

  if (summary.isPending || layout.isPending) {
    return (
      <div className="flex flex-col gap-6" aria-busy="true">
        {title}
        <Skeleton className="h-36" />
        <div className="grid gap-4 lg:grid-cols-3">
          <Skeleton className="h-80 lg:col-span-2" />
          <Skeleton className="h-80" />
        </div>
      </div>
    )
  }

  if (summary.isError || layout.isError) {
    return (
      <div className="flex flex-col gap-6">
        {title}
        <ErrorState
          onRetry={() => {
            void summary.refetch()
            void layout.refetch()
          }}
        />
      </div>
    )
  }

  if (summary.data.total === 0) {
    return (
      <div className="flex flex-col gap-6">
        {title}
        <EmptyState
          title={t('dashboard.empty.title')}
          description={t('dashboard.empty.description')}
          action={canManage ? <Button onClick={() => setIsFormOpen(true)}>{t('dashboard.empty.action')}</Button> : undefined}
        />
        {formDialog}
      </div>
    )
  }

  return (
    <>
      <DashboardBoard
        layout={layout.data}
        title={title}
        actions={canManage && <Button onClick={() => setIsFormOpen(true)}>{t('monitors.add')}</Button>}
      />
      {formDialog}
    </>
  )
}

type DashboardBoardProps = {
  layout: DashboardLayout
  title: ReactNode
  actions: ReactNode
}

function DashboardBoard({ layout, title, actions }: DashboardBoardProps) {
  const queryClient = useQueryClient()
  const { showToast } = useToast()
  const [draft, setDraft] = useState<DashboardWidget[] | null>(null)
  const [isAddOpen, setIsAddOpen] = useState(false)
  const [isRestoreOpen, setIsRestoreOpen] = useState(false)
  const isEditing = draft !== null
  const widgets = draft ?? layout.widgets

  const changePeriod = useMutation({
    mutationKey: PERIOD_MUTATION_KEY,
    mutationFn: (period: StatsPeriod) => updateDashboardLayout({ period }),
    onMutate: async (period) => {
      await queryClient.cancelQueries({ queryKey: queryKeys.dashboardLayout })
      const previous = queryClient.getQueryData<DashboardLayout>(queryKeys.dashboardLayout)
      queryClient.setQueryData<DashboardLayout>(queryKeys.dashboardLayout, (current) => (current ? { ...current, period } : current))

      return { previous }
    },
    onError: (_error, _period, context) => {
      if (queryClient.isMutating({ mutationKey: PERIOD_MUTATION_KEY }) === 1) {
        queryClient.setQueryData(queryKeys.dashboardLayout, context?.previous)
      }

      showToast(t('dashboard.saveError'), 'error')
    },
    onSuccess: (saved) => {
      if (queryClient.isMutating({ mutationKey: PERIOD_MUTATION_KEY }) === 1) {
        queryClient.setQueryData(queryKeys.dashboardLayout, saved)
      }
    },
  })

  const saveLayout = useMutation({
    mutationFn: (nextWidgets: DashboardWidget[]) => updateDashboardLayout({ period: layout.period, widgets: nextWidgets }),
    onSuccess: (saved) => {
      queryClient.setQueryData(queryKeys.dashboardLayout, saved)
      setDraft(null)
      showToast(t('dashboard.saved'))
    },
    onError: () => showToast(t('dashboard.saveError'), 'error'),
  })

  const restoreDefault = useMutation({
    mutationFn: resetDashboardLayout,
    onSuccess: (saved) => {
      queryClient.setQueryData(queryKeys.dashboardLayout, saved)
      setDraft(null)
      setIsRestoreOpen(false)
      showToast(t('dashboard.restored'))
    },
    onError: () => showToast(t('dashboard.saveError'), 'error'),
  })

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        {title}
        <div className="flex flex-wrap items-center gap-2">
          <PeriodSelector value={layout.period} onChange={(period) => changePeriod.mutate(period)} />
          {isEditing ? (
            <>
              <Button variant="secondary" onClick={() => setIsAddOpen(true)} disabled={widgets.length >= MAX_WIDGETS}>
                <PlusIcon />
                {t('dashboard.addWidget')}
              </Button>
              {!layout.is_default && (
                <Button variant="ghost" onClick={() => setIsRestoreOpen(true)}>
                  {t('dashboard.restoreDefault')}
                </Button>
              )}
              <Button variant="secondary" onClick={() => setDraft(null)} disabled={saveLayout.isPending}>
                {t('common.cancel')}
              </Button>
              <Button onClick={() => saveLayout.mutate(widgets)} isLoading={saveLayout.isPending} loadingLabel={t('dashboard.saving')}>
                {t('dashboard.saveLayout')}
              </Button>
            </>
          ) : (
            <>
              <Button variant="secondary" onClick={() => setDraft(layout.widgets)}>
                {t('dashboard.customize')}
              </Button>
              {actions}
            </>
          )}
        </div>
      </div>

      {widgets.length === 0 ? (
        <EmptyState
          title={t('dashboard.noWidgets.title')}
          description={t('dashboard.noWidgets.description')}
          action={
            <Button
              onClick={() => {
                setDraft(widgets)
                setIsAddOpen(true)
              }}
            >
              {t('dashboard.addWidget')}
            </Button>
          }
        />
      ) : (
        <Suspense fallback={<Skeleton className="h-96" />}>
          <WidgetGrid
            widgets={widgets}
            period={layout.period}
            isEditing={isEditing}
            onChange={setDraft}
            onRemove={(id) => setDraft(removeWidget(widgets, id))}
          />
        </Suspense>
      )}

      <AddWidgetDialog
        isOpen={isAddOpen}
        widgets={widgets}
        onClose={() => setIsAddOpen(false)}
        onAdd={(type, options) => setDraft(addWidget(widgets, type, options))}
      />
      <ConfirmDialog
        isOpen={isRestoreOpen}
        title={t('dashboard.restoreDialog.title')}
        description={t('dashboard.restoreDialog.description')}
        confirmLabel={t('dashboard.restoreDialog.confirm')}
        pendingLabel={t('dashboard.restoreDialog.pending')}
        isPending={restoreDefault.isPending}
        onConfirm={() => restoreDefault.mutate()}
        onClose={() => setIsRestoreOpen(false)}
      />
    </div>
  )
}

function PeriodSelector({ value, onChange }: { value: StatsPeriod; onChange: (period: StatsPeriod) => void }) {
  return (
    <div role="group" aria-label={t('dashboard.period')} className="inline-flex rounded-md border border-neutral-300 bg-surface p-0.5">
      {PERIODS.map((option) => (
        <button
          key={option}
          type="button"
          aria-pressed={value === option}
          onClick={() => onChange(option)}
          className={`rounded px-3 py-1.5 text-sm font-medium ${value === option ? 'bg-primary-700 text-white' : 'text-neutral-700 hover:bg-neutral-100'}`}
        >
          {t(`dashboard.periods.${option}`)}
        </button>
      ))}
    </div>
  )
}
