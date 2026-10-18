import { useMutation, useQueryClient } from '@tanstack/react-query'
import { createMonitor, deleteMonitor, pauseMonitor, resumeMonitor, updateMonitor } from '../../api/monitors.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import type { Monitor, MonitorInput } from '../../api/types.ts'

function useRefreshMonitors() {
  const queryClient = useQueryClient()

  return (monitor?: Monitor) => {
    if (monitor) {
      queryClient.setQueryData(queryKeys.monitor(monitor.id), monitor)
    }

    void queryClient.invalidateQueries({ queryKey: queryKeys.monitors })
    void queryClient.invalidateQueries({ queryKey: ['dashboard'] })
  }
}

export function useCreateMonitor() {
  const refresh = useRefreshMonitors()

  return useMutation({ mutationFn: (input: MonitorInput) => createMonitor(input), onSuccess: refresh })
}

export function useUpdateMonitor(id: number) {
  const refresh = useRefreshMonitors()

  return useMutation({ mutationFn: (input: Partial<MonitorInput>) => updateMonitor(id, input), onSuccess: refresh })
}

export function usePauseMonitor(id: number) {
  const refresh = useRefreshMonitors()

  return useMutation({ mutationFn: () => pauseMonitor(id), onSuccess: refresh })
}

export function useResumeMonitor(id: number) {
  const refresh = useRefreshMonitors()

  return useMutation({ mutationFn: () => resumeMonitor(id), onSuccess: refresh })
}

export function useDeleteMonitor(id: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => deleteMonitor(id),
    onSuccess: () => {
      queryClient.removeQueries({ queryKey: queryKeys.monitor(id) })
      void queryClient.invalidateQueries({ queryKey: queryKeys.monitors })
      void queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    },
  })
}
