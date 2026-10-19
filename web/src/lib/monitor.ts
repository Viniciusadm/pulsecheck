import type { Monitor } from '../api/types.ts'
import type { DisplayStatus } from '../components/ui/Badge.tsx'

export function displayStatus(monitor: Pick<Monitor, 'status' | 'is_paused' | 'is_in_maintenance'>): DisplayStatus {
  if (monitor.is_paused) {
    return 'paused'
  }

  return monitor.is_in_maintenance ? 'maintenance' : monitor.status
}
