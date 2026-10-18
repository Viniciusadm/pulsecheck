import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect } from 'react'
import { getAppConfig } from '../../api/config.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import type { createEcho } from '../../api/realtime.ts'
import type { Monitor } from '../../api/types.ts'
import { useAuth } from '../auth/AuthContext.ts'

const REFRESH_DEBOUNCE_MS = 500

type MonitorCheckedEvent = { monitor: Monitor }

export function useMonitorRealtime() {
  const { user } = useAuth()
  const queryClient = useQueryClient()
  const { data: config } = useQuery({ queryKey: queryKeys.config, queryFn: getAppConfig, staleTime: Infinity })
  const realtimeKey = config?.realtime?.key
  const isAuthenticated = user !== null

  useEffect(() => {
    if (!realtimeKey || !isAuthenticated) {
      return
    }

    let echo: ReturnType<typeof createEcho> | undefined
    let isCancelled = false
    let refreshTimer: ReturnType<typeof setTimeout> | undefined

    const refresh = () => {
      refreshTimer = undefined
      void queryClient.invalidateQueries({ queryKey: queryKeys.monitors })
      void queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    }

    void import('../../api/realtime.ts').then((realtime) => {
      if (isCancelled) {
        return
      }

      echo = realtime.createEcho(realtimeKey)
      echo.private('monitors').listen('.monitor.checked', ({ monitor }: MonitorCheckedEvent) => {
        queryClient.setQueryData(queryKeys.monitor(monitor.id), monitor)
        refreshTimer ??= setTimeout(refresh, REFRESH_DEBOUNCE_MS)
      })
    })

    return () => {
      isCancelled = true
      clearTimeout(refreshTimer)
      echo?.disconnect()
    }
  }, [realtimeKey, isAuthenticated, queryClient])
}
