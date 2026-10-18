import { useEffect, useState } from 'react'
import { formatDateTime, formatRelativeTime } from '../../lib/format.ts'

const TICK_INTERVAL_MS = 15_000

function useNow(intervalMs: number) {
  const [now, setNow] = useState(() => new Date())

  useEffect(() => {
    const timer = setInterval(() => setNow(new Date()), intervalMs)

    return () => clearInterval(timer)
  }, [intervalMs])

  return now
}

export function RelativeTime({ value, format = (time) => time }: { value: string; format?: (time: string) => string }) {
  const now = useNow(TICK_INTERVAL_MS)

  return (
    <time dateTime={value} title={formatDateTime(value)}>
      {format(formatRelativeTime(value, now))}
    </time>
  )
}
