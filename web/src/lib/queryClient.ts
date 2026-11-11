import { QueryClient } from '@tanstack/react-query'
import { isAxiosError } from 'axios'
import { queryKeys } from '../api/queryKeys.ts'

const NON_RETRYABLE_STATUSES = [401, 403, 404]

export const LIVE_DATA_STALE_TIME_MS = 30_000

export const USER_PREFERENCE_STALE_TIME_MS = 5 * 60 * 1000

export function applyQueryDefaults(client: QueryClient): QueryClient {
  client.setQueryDefaults(['dashboard'], { staleTime: LIVE_DATA_STALE_TIME_MS })
  client.setQueryDefaults(queryKeys.monitors, { staleTime: LIVE_DATA_STALE_TIME_MS })
  client.setQueryDefaults(queryKeys.dashboardLayout, { staleTime: USER_PREFERENCE_STALE_TIME_MS })

  return client
}

export const queryClient = applyQueryDefaults(
  new QueryClient({
    defaultOptions: {
      queries: {
        retry: (failureCount, error) =>
          failureCount < 1 && !(isAxiosError(error) && NON_RETRYABLE_STATUSES.includes(error.response?.status ?? 0)),
        refetchOnWindowFocus: false,
      },
    },
  }),
)
