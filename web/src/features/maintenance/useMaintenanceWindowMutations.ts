import { useMutation, useQueryClient } from '@tanstack/react-query'
import { createMaintenanceWindow, deleteMaintenanceWindow, updateMaintenanceWindow } from '../../api/maintenanceWindows.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import type { MaintenanceWindowInput } from '../../api/types.ts'

function useRefreshMaintenance() {
  const queryClient = useQueryClient()

  return () => {
    void queryClient.invalidateQueries({ queryKey: queryKeys.maintenanceWindows })
    void queryClient.invalidateQueries({ queryKey: queryKeys.monitors })
    void queryClient.invalidateQueries({ queryKey: ['dashboard'] })
  }
}

export function useSaveMaintenanceWindow(id?: number) {
  const refresh = useRefreshMaintenance()

  return useMutation({
    mutationFn: (input: MaintenanceWindowInput) => (id === undefined ? createMaintenanceWindow(input) : updateMaintenanceWindow(id, input)),
    onSuccess: refresh,
  })
}

export function useDeleteMaintenanceWindow(id: number) {
  const refresh = useRefreshMaintenance()

  return useMutation({ mutationFn: () => deleteMaintenanceWindow(id), onSuccess: refresh })
}
