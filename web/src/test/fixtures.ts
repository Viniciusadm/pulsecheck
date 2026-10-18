import type {
  AppConfig,
  DashboardLayout,
  Incident,
  LatencySeries,
  Monitor,
  MonitorStats,
  NotificationChannel,
  PaginatedResponse,
  Team,
  UptimeDay,
  User,
} from '../api/types.ts'

export const ada: User = {
  id: 1,
  name: 'Ada Lovelace',
  email: 'ada@example.com',
  role: 'admin',
  can_view_all_monitors: false,
  team_ids: [],
  email_verified_at: null,
  created_at: '2026-10-17T12:00:00Z',
}

export function makeUser(overrides: Partial<User> = {}): User {
  return {
    id: 2,
    name: 'Grace Hopper',
    email: 'grace@example.com',
    role: 'member',
    can_view_all_monitors: false,
    team_ids: [],
    email_verified_at: null,
    created_at: '2026-10-17T12:00:00Z',
    ...overrides,
  }
}

export function makeConfig(overrides: Partial<AppConfig> = {}): AppConfig {
  return {
    setup_required: false,
    setup_token_required: false,
    instance: { name: 'Acme', timezone: 'UTC', locale: 'en' },
    available_locales: ['en'],
    monitors: { allowed_intervals: [1, 5, 10], default_interval: 5, timeout_min: 1, timeout_max: 30, timeout_default: 10 },
    realtime: null,
    ...overrides,
  }
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
    notification_channel_ids: [],
    team_ids: [],
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

export function makeTeam(overrides: Partial<Team> = {}): Team {
  return {
    id: 1,
    name: 'Platform',
    users_count: 2,
    monitors_count: 3,
    created_at: '2026-10-01T12:00:00Z',
    updated_at: '2026-10-01T12:00:00Z',
    ...overrides,
  }
}

export function makeChannel(overrides: Partial<NotificationChannel> = {}): NotificationChannel {
  return {
    id: 1,
    type: 'telegram',
    name: 'My phone',
    enabled: true,
    is_default: true,
    config: { chat_id: '123456789' },
    secrets_set: ['bot_token'],
    monitors_count: 2,
    team_ids: [],
    created_at: '2026-10-01T12:00:00Z',
    updated_at: '2026-10-01T12:00:00Z',
    ...overrides,
  }
}

export function makeDashboardLayout(overrides: Partial<DashboardLayout> = {}): DashboardLayout {
  return {
    period: '24h',
    is_default: true,
    widgets: [
      { id: 'status-summary', type: 'status_summary', x: 0, y: 0, w: 12, h: 2, options: {} },
      { id: 'uptime-trend', type: 'uptime_trend', x: 0, y: 2, w: 8, h: 4, options: {} },
      { id: 'status-distribution', type: 'status_distribution', x: 8, y: 2, w: 4, h: 4, options: {} },
      { id: 'latency-trend', type: 'latency_trend', x: 0, y: 6, w: 6, h: 4, options: {} },
      { id: 'slowest-monitors', type: 'slowest_monitors', x: 6, y: 6, w: 6, h: 4, options: {} },
      { id: 'down-monitors', type: 'down_monitors', x: 0, y: 10, w: 6, h: 4, options: {} },
      { id: 'recent-incidents', type: 'recent_incidents', x: 6, y: 10, w: 6, h: 4, options: {} },
    ],
    ...overrides,
  }
}
