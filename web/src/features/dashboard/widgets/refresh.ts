import { usePollingInterval } from '../../monitors/realtimeConnection.ts'

const REFRESH_INTERVAL_MS = 30_000

export function useRefreshInterval() {
  return usePollingInterval(REFRESH_INTERVAL_MS)
}
