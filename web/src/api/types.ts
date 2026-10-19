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

export type MonitorStatus = 'pending' | 'up' | 'degraded' | 'down'

export type MonitorType = 'http' | 'keyword' | 'ssl' | 'tcp' | 'heartbeat'

export type KeywordMode = 'contains' | 'not_contains'

export type HttpMethod = 'GET' | 'HEAD' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

export type HttpAuthType = 'none' | 'basic' | 'bearer'

export type MonitorOptions = {
  method?: HttpMethod
  follow_redirects?: boolean
  expected_status_codes?: string[]
  body_content_type?: string | null
  keyword?: string | null
  keyword_mode?: KeywordMode
  case_sensitive?: boolean
  warning_days?: number
  host?: string | null
  port?: number | null
  grace_seconds?: number
}

export type MonitorSecretsInput = {
  headers?: Record<string, string> | null
  body?: string | null
  auth?: { type: HttpAuthType; username?: string; password?: string; token?: string } | null
}

export type Monitor = {
  id: number
  name: string
  type: MonitorType
  url: string | null
  display_target: string
  options: MonitorOptions
  secrets_set: string[]
  auth_type: HttpAuthType
  interval_minutes: number
  timeout_seconds: number
  latency_threshold_ms: number | null
  certificate_expires_at: string | null
  last_heartbeat_at: string | null
  heartbeat_url?: string
  badge_url?: string | null
  status: MonitorStatus
  is_paused: boolean
  is_in_maintenance: boolean
  maintenance_ends_at: string | null
  reminder_interval_minutes: number | null
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
  type?: MonitorType
  url?: string
  interval_minutes: number
  timeout_seconds?: number
  latency_threshold_ms?: number | null
  reminder_interval_minutes?: number | null
  options?: MonitorOptions
  secrets?: MonitorSecretsInput
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
  | 'keyword_missing'
  | 'keyword_present'
  | 'tls'
  | 'certificate_expired'
  | 'heartbeat_missed'
  | 'unknown'

export type CheckResult = {
  id: number
  started_at: string
  finished_at: string
  latency_ms: number | null
  status_code: number | null
  is_success: boolean
  is_degraded: boolean
  in_maintenance: boolean
  error_type: CheckErrorType | null
  error_message: string | null
  warning_type: WarningType | null
  warning_message: string | null
}

export type WarningType = 'slow_response' | 'certificate_expiring'

export type IncidentKind = 'outage' | 'degradation'

export type Incident = {
  id: number
  kind: IncidentKind
  started_at: string
  resolved_at: string | null
  cause: string
  state: 'open' | 'resolved'
  duration_seconds: number
  acknowledged_at: string | null
  acknowledged_by?: { id: number; name: string } | null
  reminder_count: number
  notes_count?: number
  monitor?: { id: number; name: string }
}

export type IncidentNote = {
  id: number
  body: string
  user: { id: number; name: string } | null
  can_delete: boolean
  created_at: string
}

export type StatusPageMonitor = {
  monitor_id: number
  name: string
  display_name: string | null
  status: MonitorStatus
}

export type StatusPage = {
  id: number
  name: string
  slug: string
  description: string | null
  is_published: boolean
  show_latency: boolean
  show_incidents: boolean
  uptime_days: 30 | 60 | 90
  public_path: string
  monitors: StatusPageMonitor[]
  hidden_monitors_count: number
  team_ids: number[]
  created_at: string
  updated_at: string
}

export type StatusPageInput = {
  name: string
  slug: string
  description: string | null
  is_published: boolean
  show_latency: boolean
  show_incidents: boolean
  uptime_days: number
  monitors: { monitor_id: number; display_name: string | null }[]
  team_ids?: number[]
}

export type PublicMonitorStatus = MonitorStatus | 'paused' | 'maintenance'

export type PublicOverallStatus = 'operational' | 'degraded' | 'partial_outage' | 'major_outage' | 'maintenance' | 'unknown'

export type PublicStatusPage = {
  name: string
  description: string | null
  generated_at: string
  uptime_days: number
  overall_status: PublicOverallStatus
  monitors: {
    key: number
    name: string
    status: PublicMonitorStatus
    uptime_percentage: number | null
    latency_ms: number | null
    days: { date: string; uptime_percentage: number | null }[]
  }[]
  incidents: { monitor_key: number; started_at: string; resolved_at: string | null; duration_seconds: number }[]
}

export type MaintenanceWindowType = 'one_off' | 'recurring'

export type MaintenanceWindow = {
  id: number
  name: string
  type: MaintenanceWindowType
  starts_at: string | null
  ends_at: string | null
  weekdays: number[] | null
  start_time: string | null
  end_time: string | null
  timezone: string | null
  monitors: { id: number; name: string }[]
  monitor_ids: number[]
  team_ids: number[]
  active_until: string | null
  next_starts_at: string | null
  created_at: string
  updated_at: string
}

export type MaintenanceWindowInput = {
  name: string
  type: MaintenanceWindowType
  starts_at?: string
  ends_at?: string
  weekdays?: number[]
  start_time?: string
  end_time?: string
  timezone?: string
  monitor_ids: number[]
  team_ids: number[]
}

export type StatsPeriod = '24h' | '7d' | '30d'

export type MonitorStats = {
  period: StatsPeriod
  from: string
  to: string
  total_checks: number
  successful_checks: number
  degraded_checks: number
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
  degraded_checks: number
  maintenance_checks: number
  uptime_percentage: number | null
}

export type DashboardSummary = {
  total: number
  up: number
  degraded: number
  down: number
  pending: number
  maintenance: number
  paused: number
}

export type DashboardWidgetType =
  | 'status_summary'
  | 'status_distribution'
  | 'down_monitors'
  | 'recent_incidents'
  | 'uptime_trend'
  | 'latency_trend'
  | 'slowest_monitors'
  | 'incident_timeline'
  | 'error_breakdown'
  | 'monitor_latency'

export type DashboardWidget = {
  id: string
  type: DashboardWidgetType
  x: number
  y: number
  w: number
  h: number
  options: { monitor_id?: number }
}

export type DashboardLayout = {
  period: StatsPeriod
  widgets: DashboardWidget[]
  is_default: boolean
}

export type DashboardLayoutInput = {
  period: StatsPeriod
  widgets?: DashboardWidget[]
}

export type UptimeTrendBucket = {
  bucket_start: string
  checks: number
  failed_checks: number
  uptime_percentage: number | null
}

export type LatencyTrendBucket = {
  bucket_start: string
  average_latency_ms: number | null
  p95_latency_ms: number | null
}

export type SlowMonitor = {
  monitor_id: number
  name: string
  average_latency_ms: number
}

export type IncidentTimelineBucket = {
  bucket_start: string
  incidents: number
}

export type ErrorBreakdownItem = {
  error_type: CheckErrorType
  failed_checks: number
}

export type InstanceSettings = {
  name: string
  timezone: string
  locale: string
}

export type MailDriver = 'smtp' | 'mailgun' | 'postmark' | 'resend' | 'ses'

export type MailConfig = Record<string, string | number | null>

export type MailSettings = {
  source: 'panel' | 'environment'
  environment_driver: string
  driver: MailDriver | null
  from_address: string | null
  from_name: string | null
  config: MailConfig
  secrets_set: string[]
}

export type MailSettingsInput = {
  driver: MailDriver
  from_address: string
  from_name: string | null
  config: MailConfig
}

export type AppConfig = {
  setup_required: boolean
  setup_token_required: boolean
  instance: InstanceSettings
  available_locales: string[]
  monitors: {
    allowed_intervals: number[]
    heartbeat_intervals: number[]
    keyword_max_body_bytes: number
    reminder_intervals: number[]
    default_interval: number
    timeout_min: number
    timeout_max: number
    timeout_default: number
  }
  realtime: { key: string } | null
}

export type NotificationChannelType = 'telegram' | 'webhook' | 'slack' | 'discord' | 'email'

export type NotificationEvent = 'down' | 'recovered' | 'closed' | 'degraded' | 'degradation_resolved' | 'reminder'

export type MessageFormat = 'detailed' | 'compact'

export type AlertSeverity = 'critical' | 'warning' | 'resolved' | 'info'

export type NotificationChannel = {
  id: number
  type: NotificationChannelType
  name: string
  enabled: boolean
  is_default: boolean
  events: NotificationEvent[]
  quiet_hours_start: string | null
  quiet_hours_end: string | null
  quiet_hours_allow_critical: boolean
  message_format: MessageFormat
  config: Record<string, string | boolean>
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
  events?: NotificationEvent[]
  quiet_hours_start?: string | null
  quiet_hours_end?: string | null
  quiet_hours_allow_critical?: boolean
  message_format?: MessageFormat
  config: Record<string, string | boolean | null | Record<string, string>>
  team_ids?: number[]
}

export type AlertPreview = {
  event: string
  severity: AlertSeverity
  title: string
  summary: string | null
  fields: { label: string; value: string }[]
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
  user_ids?: number[]
  monitor_ids?: number[]
  created_at: string
  updated_at: string
}

export type TeamInput = {
  name: string
  user_ids?: number[]
  monitor_ids?: number[]
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
