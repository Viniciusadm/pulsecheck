import { useQuery, useQueryClient, type InfiniteData, type Query, type QueryClient } from '@tanstack/react-query'
import { useEffect } from 'react'
import { getAppConfig } from '../../api/config.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import type { createEcho } from '../../api/realtime.ts'
import type { Incident, Monitor, PaginatedResponse } from '../../api/types.ts'
import { useAuth } from '../auth/AuthContext.ts'
import { setRealtimeConnected } from './realtimeConnection.ts'

const REFRESH_DEBOUNCE_MS = 500
const DASHBOARD_REFRESH_MS = 30_000
const REALTIME_INVALIDATION = { cancelRefetch: false } as const

type CheckedMonitor = Omit<Monitor, 'notification_channel_ids'>

type MonitorCheckedEvent = { monitor: CheckedMonitor }

type IncidentUpdatedEvent = { incident: Incident }

function statusSignature(monitor: Pick<Monitor, 'status' | 'is_paused' | 'is_in_maintenance'>): string {
  return `${monitor.status}|${monitor.is_paused}|${monitor.is_in_maintenance}`
}

function cachedMonitor(queryClient: QueryClient, monitorId: number): Monitor | undefined {
  const detail = queryClient.getQueryData<Monitor>(queryKeys.monitor(monitorId))

  if (detail) {
    return detail
  }

  const allMonitors = queryClient.getQueryData<Monitor[]>(queryKeys.allMonitors)
  const fromAll = allMonitors?.find((monitor) => monitor.id === monitorId)

  if (fromAll) {
    return fromAll
  }

  for (const [, page] of queryClient.getQueriesData<PaginatedResponse<Monitor>>({ queryKey: queryKeys.monitorLists })) {
    const fromList = page?.data.find((monitor) => monitor.id === monitorId)

    if (fromList) {
      return fromList
    }
  }

  return undefined
}

function isMultiPageCheckHistory(query: Query): boolean {
  const pageCount = (query.state.data as InfiniteData<unknown> | undefined)?.pages?.length ?? 0

  return query.queryKey[2] === 'checks' && pageCount > 1
}

function mergeMonitor(monitors: Monitor[], checked: CheckedMonitor): Monitor[] {
  return monitors.map((monitor) => (monitor.id === checked.id ? { ...monitor, ...checked } : monitor))
}

function applyCheckedMonitor(queryClient: QueryClient, checked: CheckedMonitor): boolean {
  const previous = cachedMonitor(queryClient, checked.id)

  queryClient.setQueryData<Monitor>(queryKeys.monitor(checked.id), (current) => (current ? { ...current, ...checked } : current))
  queryClient.setQueryData<Monitor[]>(queryKeys.allMonitors, (current) => (current ? mergeMonitor(current, checked) : current))
  queryClient.setQueriesData<PaginatedResponse<Monitor>>({ queryKey: queryKeys.monitorLists }, (current) =>
    current ? { ...current, data: mergeMonitor(current.data, checked) } : current,
  )

  return previous === undefined || statusSignature(previous) !== statusSignature(checked)
}

export function useMonitorRealtime() {
  const { user } = useAuth()
  const queryClient = useQueryClient()
  const { data: config } = useQuery({ queryKey: queryKeys.config, queryFn: getAppConfig, staleTime: Infinity })
  const realtimeKey = config?.realtime?.key
  const userId = user?.id

  useEffect(() => {
    if (!realtimeKey || userId === undefined) {
      return
    }

    let echo: ReturnType<typeof createEcho> | undefined
    let stopWatchingConnection: (() => void) | undefined
    let isCancelled = false
    let refreshTimer: ReturnType<typeof setTimeout> | undefined
    let dashboardTimer: ReturnType<typeof setTimeout> | undefined
    let hasStatusChange = false
    const checkedMonitorIds = new Set<number>()

    const refreshDashboard = () => {
      dashboardTimer = undefined
      void queryClient.invalidateQueries({ queryKey: ['dashboard'] }, REALTIME_INVALIDATION)
    }

    const refresh = () => {
      refreshTimer = undefined

      if (hasStatusChange) {
        void queryClient.invalidateQueries({ queryKey: queryKeys.monitorLists }, REALTIME_INVALIDATION)
        void queryClient.invalidateQueries({ queryKey: queryKeys.summary }, REALTIME_INVALIDATION)
        void queryClient.invalidateQueries({ queryKey: queryKeys.recentIncidents }, REALTIME_INVALIDATION)
        hasStatusChange = false
      }

      for (const monitorId of checkedMonitorIds) {
        const monitorKey = queryKeys.monitor(monitorId)
        void queryClient.invalidateQueries(
          { queryKey: monitorKey, predicate: (query) => query.queryKey.length > monitorKey.length && !isMultiPageCheckHistory(query) },
          REALTIME_INVALIDATION,
        )
      }

      checkedMonitorIds.clear()
      dashboardTimer ??= setTimeout(refreshDashboard, DASHBOARD_REFRESH_MS)
    }

    import('../../api/realtime.ts')
      .then((realtime) => {
        if (isCancelled) {
          return
        }

        echo = realtime.createEcho(realtimeKey)
        stopWatchingConnection = echo.connector.onConnectionChange((status) => setRealtimeConnected(status === 'connected'))
        echo
          .private(`users.${userId}.monitors`)
          .listen('.monitor.checked', ({ monitor }: MonitorCheckedEvent) => {
            hasStatusChange = applyCheckedMonitor(queryClient, monitor) || hasStatusChange
            checkedMonitorIds.add(monitor.id)
            refreshTimer ??= setTimeout(refresh, REFRESH_DEBOUNCE_MS)
          })
          .listen('.incident.updated', ({ incident }: IncidentUpdatedEvent) => {
            void queryClient.invalidateQueries({ queryKey: queryKeys.incidentNotes(incident.id) })

            if (incident.monitor) {
              checkedMonitorIds.add(incident.monitor.id)
            }

            hasStatusChange = true
            refreshTimer ??= setTimeout(refresh, REFRESH_DEBOUNCE_MS)
          })
      })
      .catch(() => setRealtimeConnected(false))

    return () => {
      isCancelled = true
      clearTimeout(refreshTimer)
      clearTimeout(dashboardTimer)
      stopWatchingConnection?.()
      setRealtimeConnected(false)
      echo?.disconnect()
    }
  }, [realtimeKey, userId, queryClient])
}
