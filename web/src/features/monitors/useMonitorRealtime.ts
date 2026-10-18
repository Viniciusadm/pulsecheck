import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect } from 'react'
import { getAppConfig } from '../../api/config.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import type { createEcho } from '../../api/realtime.ts'
import type { Monitor } from '../../api/types.ts'
import { useAuth } from '../auth/AuthContext.ts'

const REFRESH_DEBOUNCE_MS = 500

type MonitorCheckedEvent = { monitor: Omit<Monitor, 'notification_channel_ids'> }

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
    let isCancelled = false
    let refreshTimer: ReturnType<typeof setTimeout> | undefined
    const checkedMonitorIds = new Set<number>()

    const refresh = () => {
      refreshTimer = undefined
      void queryClient.invalidateQueries({ queryKey: queryKeys.monitorLists })
      void queryClient.invalidateQueries({ queryKey: queryKeys.allMonitors })
      void queryClient.invalidateQueries({ queryKey: ['dashboard'] })

      for (const monitorId of checkedMonitorIds) {
        const monitorKey = queryKeys.monitor(monitorId)
        void queryClient.invalidateQueries({ queryKey: monitorKey, predicate: (query) => query.queryKey.length > monitorKey.length })
      }

      checkedMonitorIds.clear()
    }

    void import('../../api/realtime.ts').then((realtime) => {
      if (isCancelled) {
        return
      }

      echo = realtime.createEcho(realtimeKey)
      echo.private(`users.${userId}.monitors`).listen('.monitor.checked', ({ monitor }: MonitorCheckedEvent) => {
        queryClient.setQueryData<Monitor>(queryKeys.monitor(monitor.id), (current) => (current ? { ...current, ...monitor } : current))
        checkedMonitorIds.add(monitor.id)
        refreshTimer ??= setTimeout(refresh, REFRESH_DEBOUNCE_MS)
      })
    })

    return () => {
      isCancelled = true
      clearTimeout(refreshTimer)
      echo?.disconnect()
    }
  }, [realtimeKey, userId, queryClient])
}
