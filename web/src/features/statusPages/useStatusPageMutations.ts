import { useMutation, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '../../api/queryKeys.ts'
import { createStatusPage, deleteStatusPage, updateStatusPage } from '../../api/statusPages.ts'
import type { StatusPageInput } from '../../api/types.ts'

export function useSaveStatusPage(id?: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (input: StatusPageInput) => (id === undefined ? createStatusPage(input) : updateStatusPage(id, input)),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: queryKeys.statusPages }),
  })
}

export function useDeleteStatusPage(id: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => deleteStatusPage(id),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: queryKeys.statusPages }),
  })
}
