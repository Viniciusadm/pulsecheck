export type UserRole = 'admin' | 'manager' | 'member'

export type User = {
  id: number
  name: string
  email: string
  role: UserRole
  can_view_all_monitors: boolean
  team_ids: number[]
  email_verified_at: string | null
  created_at: string
}

export type DataResponse<T> = {
  data: T
}

export type PaginatedResponse<T> = {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export type CursorPaginatedResponse<T> = {
  data: T[]
  meta: {
    per_page: number
    next_cursor: string | null
    prev_cursor: string | null
  }
}

export type ValidationErrorResponse = {
  message: string
  errors: Record<string, string[]>
}

export type MonitorStatus = 'pending' | 'up' | 'down'

export type Monitor = {
  id: number
  name: string
  url: string
  interval_minutes: number
  timeout_seconds: number
  status: MonitorStatus
  is_paused: boolean
  last_status_code: number | null
  last_latency_ms: number | null
  last_checked_at: string | null
  last_failed_at: string | null
  next_check_at: string | null
  notification_channel_ids: number[]
  team_ids: number[]
  created_at: string
  updated_at: string
}

export type MonitorInput = {
  name: string
  url: string
  interval_minutes: number
  timeout_seconds: number
  notification_channel_ids?: number[]
  team_ids?: number[]
}

export type CheckErrorType =
  | 'timeout'
  | 'dns'
  | 'connection'
  | 'http_status'
  | 'blocked_destination'
  | 'too_many_redirects'
  | 'unknown'

export type CheckResult = {
  id: number
  started_at: string
  finished_at: string
  latency_ms: number | null
  status_code: number | null
  is_success: boolean
  error_type: CheckErrorType | null
  error_message: string | null
}

export type Incident = {
  id: number
  started_at: string
  resolved_at: string | null
  cause: string
  state: 'open' | 'resolved'
  duration_seconds: number
  monitor?: { id: number; name: string }
}

export type StatsPeriod = '24h' | '7d' | '30d'

export type MonitorStats = {
  period: StatsPeriod
  from: string
  to: string
  total_checks: number
  successful_checks: number
  uptime_percentage: number | null
  average_latency_ms: number | null
  p95_latency_ms: number | null
}

export type LatencyBucket = {
  bucket_start: string
  average_latency_ms: number | null
  checks: number
  failed_checks: number
}

export type LatencySeries = {
  period: StatsPeriod
  bucket_minutes: number
  buckets: LatencyBucket[]
}

export type UptimeDay = {
  date: string
  total_checks: number
  successful_checks: number
  uptime_percentage: number | null
}

export type DashboardSummary = {
  total: number
  up: number
  down: number
  pending: number
  paused: number
}

export type InstanceSettings = {
  name: string
  timezone: string
  locale: string
}

export type AppConfig = {
  setup_required: boolean
  setup_token_required: boolean
  instance: InstanceSettings
  available_locales: string[]
  monitors: {
    allowed_intervals: number[]
    default_interval: number
    timeout_min: number
    timeout_max: number
    timeout_default: number
  }
  realtime: { key: string } | null
}

export type NotificationChannelType = 'telegram' | 'webhook' | 'slack' | 'discord' | 'email'

export type NotificationChannel = {
  id: number
  type: NotificationChannelType
  name: string
  enabled: boolean
  is_default: boolean
  config: Record<string, string>
  secrets_set: string[]
  monitors_count: number
  team_ids: number[]
  created_at: string
  updated_at: string
}

export type NotificationChannelInput = {
  type?: NotificationChannelType
  name: string
  enabled: boolean
  is_default: boolean
  config: Record<string, string | Record<string, string>>
  team_ids?: number[]
}

export type UserInput = {
  name: string
  email: string
  role: UserRole
  can_view_all_monitors: boolean
  team_ids: number[]
  password?: string
  password_confirmation?: string
}

export type Team = {
  id: number
  name: string
  users_count?: number
  monitors_count?: number
  created_at: string
  updated_at: string
}

export type TeamInput = {
  name: string
}

export type SetupInput = {
  instance_name: string
  timezone: string
  locale: string
  name: string
  email: string
  password: string
  password_confirmation: string
  token?: string
}
