import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useCallback, useEffect, useMemo, type ReactNode } from 'react'
import { fetchCurrentUser } from '../../api/auth.ts'
import { onUnauthorized } from '../../api/client.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import type { User } from '../../api/types.ts'
import { AuthContext, currentUserQueryKey } from './AuthContext.ts'

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()
  const { data, isPending } = useQuery({
    queryKey: currentUserQueryKey,
    queryFn: fetchCurrentUser,
    retry: false,
    staleTime: 5 * 60 * 1000,
  })

  const setUser = useCallback(
    (user: User | null) => {
      if (user === null) {
        queryClient.removeQueries({ predicate: (query) => query.queryKey[0] !== currentUserQueryKey[0] && query.queryKey[0] !== queryKeys.config[0] })
      }

      queryClient.setQueryData(currentUserQueryKey, user)
    },
    [queryClient],
  )

  useEffect(() => {
    onUnauthorized(() => setUser(null))
  }, [setUser])

  const value = useMemo(() => ({ user: data ?? null, isLoading: isPending, setUser }), [data, isPending, setUser])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
