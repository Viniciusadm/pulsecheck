import type { Incident, LatencySeries, Monitor, MonitorStats, PaginatedResponse, UptimeDay, User } from '../api/types.ts'

export const ada: User = {
  id: 1,
  name: 'Ada Lovelace',
  email: 'ada@example.com',
  email_verified_at: null,
  created_at: '2026-10-17T12:00:00Z',
}

export function makeMonitor(overrides: Partial<Monitor> = {}): Monitor {
  return {
    id: 7,
    name: 'Shop API',
    url: 'https://shop.example.com/health',
    interval_minutes: 5,
    timeout_seconds: 10,
    status: 'up',
    is_paused: false,
    last_status_code: 200,
    last_latency_ms: 182,
    last_checked_at: new Date(Date.now() - 120_000).toISOString(),
    last_failed_at: null,
    next_check_at: null,
    created_at: '2026-10-01T12:00:00Z',
    updated_at: '2026-10-01T12:00:00Z',
    ...overrides,
  }
}

export function paginated<T>(data: T[]): PaginatedResponse<T> {
  return { data, meta: { current_page: 1, last_page: 1, per_page: 20, total: data.length } }
}

export const openIncident: Incident = {
  id: 3,
  started_at: '2026-10-17T10:00:00Z',
  resolved_at: null,
  cause: 'timeout: Connection timed out after 10000 milliseconds',
  state: 'open',
  duration_seconds: 600,
  monitor: { id: 7, name: 'Shop API' },
}

export function makeStats(overrides: Partial<MonitorStats> = {}): MonitorStats {
  return {
    period: '24h',
    from: '2026-10-16T12:00:00Z',
    to: '2026-10-17T12:00:00Z',
    total_checks: 0,
    successful_checks: 0,
    uptime_percentage: null,
    average_latency_ms: null,
    p95_latency_ms: null,
    ...overrides,
  }
}

export const emptyLatency: LatencySeries = { period: '24h', bucket_minutes: 15, buckets: [] }

export const emptyUptimeDays: UptimeDay[] = [
  { date: '2026-10-16', total_checks: 0, successful_checks: 0, uptime_percentage: null },
  { date: '2026-10-17', total_checks: 0, successful_checks: 0, uptime_percentage: null },
]
