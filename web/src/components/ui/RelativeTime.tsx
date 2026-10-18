import { useSyncExternalStore } from 'react'
import { formatDateTime, formatRelativeTime } from '../../lib/format.ts'

const TICK_INTERVAL_MS = 15_000

const listeners = new Set<() => void>()
let now = new Date()
let timer: ReturnType<typeof setInterval> | undefined

function subscribe(listener: () => void) {
  if (listeners.size === 0) {
    now = new Date()
    timer = setInterval(() => {
      now = new Date()
      listeners.forEach((notify) => notify())
    }, TICK_INTERVAL_MS)
  }

  listeners.add(listener)

  return () => {
    listeners.delete(listener)

    if (listeners.size === 0) {
      clearInterval(timer)
    }
  }
}

function useNow() {
  return useSyncExternalStore(subscribe, () => now)
}

export function RelativeTime({ value, format = (time) => time }: { value: string; format?: (time: string) => string }) {
  const now = useNow()

  return (
    <time dateTime={value} title={formatDateTime(value)}>
      {format(formatRelativeTime(value, now))}
    </time>
  )
}
