import { QueryClient } from '@tanstack/react-query'
import { isAxiosError } from 'axios'

const NON_RETRYABLE_STATUSES = [401, 403, 404]

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: (failureCount, error) =>
        failureCount < 1 && !(isAxiosError(error) && NON_RETRYABLE_STATUSES.includes(error.response?.status ?? 0)),
      refetchOnWindowFocus: false,
    },
  },
})
