import type { UseQueryResult } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { ErrorState } from '../../../components/ui/ErrorState.tsx'
import { Skeleton } from '../../../components/ui/Skeleton.tsx'

type QueryContentProps<T> = {
  query: UseQueryResult<T>
  children: (data: T) => ReactNode
}

export function QueryContent<T>({ query, children }: QueryContentProps<T>) {
  if (query.isPending) {
    return <Skeleton className="h-full min-h-24" />
  }

  if (query.data === undefined) {
    return <ErrorState onRetry={() => void query.refetch()} />
  }

  return children(query.data)
}

export function EmptyChart({ message }: { message: string }) {
  return <p className="flex h-full min-h-24 items-center justify-center text-center text-neutral-500">{message}</p>
}
