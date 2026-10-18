import type { Monitor } from '../api/types.ts'
import type { DisplayStatus } from '../components/ui/Badge.tsx'

export function displayStatus(monitor: Pick<Monitor, 'status' | 'is_paused'>): DisplayStatus {
  return monitor.is_paused ? 'paused' : monitor.status
}

export function hostOf(url: string): string {
  try {
    return new URL(url).host
  } catch {
    return url
  }
}
