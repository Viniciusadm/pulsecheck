import { useSyncExternalStore } from 'react'

let isConnected = false
const listeners = new Set<() => void>()

export function setRealtimeConnected(connected: boolean) {
  if (connected === isConnected) {
    return
  }

  isConnected = connected
  listeners.forEach((listener) => listener())
}

function subscribe(listener: () => void) {
  listeners.add(listener)

  return () => {
    listeners.delete(listener)
  }
}

export function usePollingInterval(intervalMs: number): number | false {
  const connected = useSyncExternalStore(subscribe, () => isConnected)

  return connected ? false : intervalMs
}
