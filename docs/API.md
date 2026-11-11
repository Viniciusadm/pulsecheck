# PulseCheck REST API

Everything the web interface does goes through this JSON API, so anything you can click you can also automate: create monitors from a deploy pipeline, pause them during a migration, pull incidents into a report or scrape metrics with Prometheus.

- [Authentication](#authentication)
- [Conventions](#conventions)
- [Monitors](#monitors)
- [Incidents](#incidents)
- [Maintenance windows](#maintenance-windows)
- [Notification channels](#notification-channels)
- [Status pages](#status-pages)
- [Dashboard and metrics](#dashboard-and-metrics)
- [Prometheus](#prometheus)
- [Administration](#administration)
- [Heartbeats and badges](#heartbeats-and-badges)

## Authentication

Create a token under **My account › API tokens** and send it as a bearer token:

```sh
export PULSECHECK_URL=https://status.example.com
export PULSECHECK_TOKEN='1|pulse_…'

curl -H "Authorization: Bearer $PULSECHECK_TOKEN" -H "Accept: application/json" "$PULSECHECK_URL/api/monitors"
```

- A token acts as the person who created it, with the same role and teams. A member's token can read but never change data, whatever its access.
- **Read only** tokens can call every `GET` endpoint. **Read and write** tokens can also create, change and delete.
- Tokens can expire after 7, 30, 90 or 365 days, or never. Expired tokens are rejected and removed daily.
- Tokens start with `pulse_` so secret scanners (GitHub, GitLab, Gitleaks) can spot them if they leak. Revoke a token at any time from the same page.
- Tokens cannot manage the account itself: changing the password, two-factor authentication and API tokens require logging in through the browser.
- Requests are limited to 600 per minute per user.

## Conventions

- Send and expect JSON (`Content-Type: application/json`, `Accept: application/json`).
- Single resources come wrapped in `data`; lists are paginated with `data`, `links` and `meta` (`current_page`, `last_page`, `per_page`, `total`). Use `?page=N`.
- Times are ISO 8601 in UTC.
- Validation errors answer `422` with `message` and `errors` (field → messages). Missing permissions answer `403`; resources outside your teams answer `404`; rate limits answer `429`.
- Secrets (monitor headers, request bodies, credentials, channel tokens and webhook URLs) are write-only. They are never returned; `secrets_set` lists which ones are stored. Leave a secret out of an update to keep it.

## Monitors

| Method and path | Description |
|---|---|
| `GET /api/monitors` | List monitors. Filters: `status` (`up`, `degraded`, `down`, `pending`, `maintenance`, `paused`) and `search` (name or address). 20 per page. |
| `POST /api/monitors` | Create a monitor. |
| `GET /api/monitors/{id}` | One monitor. |
| `PATCH /api/monitors/{id}` | Change a monitor; send only the fields to change. |
| `DELETE /api/monitors/{id}` | Delete a monitor with its history. |
| `POST /api/monitors/{id}/pause` · `/resume` | Pause or resume checks. |
| `POST /api/monitors/bulk` | `{"action": "pause" \| "resume" \| "delete", "ids": [1, 2]}` for up to 100 monitors. Monitors you cannot change are skipped; the answer says how many were affected. |
| `GET /api/monitors/{id}/stats?period=24h` | Uptime, average and 95th percentile response time (`24h`, `7d`, `30d`). |
| `GET /api/monitors/{id}/latency?period=24h` | Response time series. |
| `GET /api/monitors/{id}/uptime-history?days=30` | Daily uptime. |
| `GET /api/monitors/{id}/checks?from=…&to=…` | Check history. |
| `GET /api/monitors/{id}/incidents` | Incidents of the monitor. |
| `POST /api/monitors/{id}/heartbeat-token` | New heartbeat URL (heartbeat monitors). |
| `POST /api/monitors/{id}/badge` · `DELETE …/badge` | Create, regenerate or turn off the uptime badge. |

Fields for `POST` and `PATCH`:

| Field | Notes |
|---|---|
| `name` | Required, up to 100 characters. |
| `type` | `http` (default), `keyword`, `tcp`, `ssl` or `heartbeat`. Cannot be changed later. |
| `url` | For `http`, `keyword` and `ssl`. Public `http`/`https` address. |
| `options` | Type-specific: `method`, `expected_status_codes` (e.g. `["200-299", "301"]`), `follow_redirects`, `body_content_type` for HTTP; `keyword`, `keyword_mode` (`contains`/`not_contains`) and `case_sensitive` for keyword; `host` and `port` for TCP; `warning_days` for SSL; `grace_seconds` for heartbeat. |
| `secrets` | HTTP only: `headers` (object), `body` (string), `auth` (`{"type": "basic", "username": "…", "password": "…"}` or `{"type": "bearer", "token": "…"}`). |
| `interval_minutes` | `1`, `5` or `10` (heartbeat: up to `1440`). |
| `timeout_seconds` | 1–30, shorter than the interval. |
| `latency_threshold_ms` | Optional. Slower responses mark the monitor degraded. |
| `reminder_interval_minutes` | Optional repeated alert while down: `15`, `30`, `60`, `120` or `240`. |
| `notification_channel_ids` | Channels that receive this monitor's alerts. |
| `team_ids` | Teams that can see the monitor. Managers must pick at least one of their teams. |

```sh
curl -X POST "$PULSECHECK_URL/api/monitors" \
  -H "Authorization: Bearer $PULSECHECK_TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"name": "Checkout API", "url": "https://api.example.com/health", "interval_minutes": 1, "latency_threshold_ms": 800,
       "options": {"expected_status_codes": ["200"]}, "secrets": {"headers": {"Authorization": "Bearer internal"}}}'
```

Pause everything tagged in a deploy and resume it afterwards:

```sh
ids=$(curl -s -H "Authorization: Bearer $PULSECHECK_TOKEN" "$PULSECHECK_URL/api/monitors?search=checkout" | jq -c '[.data[].id]')
curl -X POST "$PULSECHECK_URL/api/monitors/bulk" -H "Authorization: Bearer $PULSECHECK_TOKEN" -H "Content-Type: application/json" \
  -d "{\"action\": \"pause\", \"ids\": $ids}"
```

For planned work, a [maintenance window](#maintenance-windows) is usually better: checks keep running but no alerts go out.

## Incidents

| Method and path | Description |
|---|---|
| `GET /api/incidents` | Recent incidents across the monitors you can see. |
| `POST /api/incidents/{id}/acknowledge` | Acknowledge an open incident; stops repeated alerts. |
| `GET /api/incidents/{id}/notes` · `POST …/notes` | Read or add notes (`{"body": "…"}`). |
| `DELETE /api/incidents/{id}/notes/{note}` | Delete your note. |

## Maintenance windows

| Method and path | Description |
|---|---|
| `GET /api/maintenance-windows` | List windows. |
| `POST /api/maintenance-windows` | Create a window. |
| `GET` · `PATCH` · `DELETE /api/maintenance-windows/{id}` | Read, change or delete one. |

One-off window for a deploy:

```json
{
  "name": "Database upgrade",
  "type": "one_off",
  "starts_at": "2026-11-20T02:00:00Z",
  "ends_at": "2026-11-20T03:00:00Z",
  "monitor_ids": [1, 2],
  "team_ids": []
}
```

Weekly windows use `"type": "recurring"`, `weekdays` (ISO numbers, `1` = Monday … `7` = Sunday), `start_time` and `end_time` (`HH:MM`, may cross midnight) and an IANA `timezone`.

## Notification channels

| Method and path | Description |
|---|---|
| `GET /api/notification-channels` | List channels. Members see names only. |
| `POST /api/notification-channels` | Create a channel. |
| `PATCH` · `DELETE /api/notification-channels/{id}` | Change or delete one. |
| `POST /api/notification-channels/{id}/test` | Send a test message. |
| `GET /api/notification-channels/preview?event=down&message_format=detailed` | How an alert will look. |

`type` and its `config`:

| Type | `config` |
|---|---|
| `telegram` | `bot_token`, `chat_id`, optional `silent` |
| `slack` | `webhook_url`, optional `mention` (`here` or `channel`) |
| `discord` | `webhook_url`, optional `mention_role_id` |
| `microsoft_teams` | `webhook_url` (Teams Workflows) |
| `google_chat` | `webhook_url` |
| `ntfy` | `topic`, optional `server_url` and `access_token` |
| `pushover` | `user_key`, `app_token`, optional `device` |
| `pagerduty` | `routing_key` (Events API v2 integration key) |
| `email` | `email` |
| `webhook` | `url`, optional `headers` |

Common fields: `name`, `enabled`, `is_default`, `events`, `quiet_hours_start`, `quiet_hours_end`, `quiet_hours_allow_critical`, `message_format` (`detailed` or `compact`) and `team_ids`.

## Status pages

| Method and path | Description |
|---|---|
| `GET /api/status-pages` · `POST` | List or create pages. |
| `GET` · `PATCH` · `DELETE /api/status-pages/{id}` | Read, change or delete one. |
| `GET /api/public/status-pages/{slug}` | Public data of a published page, no authentication. |

Fields: `name`, `slug`, `description`, `custom_domain` (a host name such as `status.example.com`, or `null`), `is_published`, `show_latency`, `show_incidents`, `uptime_days` (`30`, `60` or `90`), `monitors` (`[{"monitor_id": 1, "display_name": "API"}]`, in display order) and `team_ids`. Responses include `public_url`, the address where the page is published.

## Dashboard and metrics

| Method and path | Description |
|---|---|
| `GET /api/dashboard/summary` | Monitors per status. |
| `GET /api/dashboard/uptime-trend?period=7d` | Uptime over time. |
| `GET /api/dashboard/latency-trend?period=7d` | Average and p95 response time over time. |
| `GET /api/dashboard/slowest-monitors?period=7d` | Slowest monitors. |
| `GET /api/dashboard/incident-timeline?period=30d` | Incidents per bucket. |
| `GET /api/dashboard/error-breakdown?period=7d` | Failed checks by cause. |

## Prometheus

`GET /api/metrics` returns the monitors you can see in the Prometheus text format. Use a read-only token:

```yaml
scrape_configs:
  - job_name: pulsecheck
    scheme: https
    metrics_path: /api/metrics
    authorization:
      credentials: "1|pulse_xxxxxxxx"   # the token exactly as shown when it was created
    static_configs:
      - targets: ["status.example.com"]
```

| Metric | Labels | Meaning |
|---|---|---|
| `pulsecheck_monitor_up` | `id`, `name`, `type` | 1 when up or degraded, 0 when down. Absent while pending or paused. |
| `pulsecheck_monitor_status` | `id`, `name`, `type`, `status` | 1 for the current status (`up`, `degraded`, `down`, `pending`, `paused`, `maintenance`), 0 for the others. |
| `pulsecheck_monitor_response_time_seconds` | `id`, `name`, `type` | Response time of the last check. |
| `pulsecheck_monitor_last_check_timestamp_seconds` | `id`, `name`, `type` | Unix time of the last check. |
| `pulsecheck_monitor_uptime_ratio` | `id`, `name`, `type`, `window` | Successful checks over the last 24 hours, maintenance excluded. |
| `pulsecheck_monitor_certificate_expiry_timestamp_seconds` | `id`, `name`, `type` | When the TLS certificate expires (SSL monitors). |
| `pulsecheck_monitor_open_incident` | `id`, `name`, `type` | 1 while an incident is open. |
| `pulsecheck_monitors` | | Number of monitors visible to the token owner. |

Example alert for certificates expiring within a week:

```yaml
- alert: CertificateExpiringSoon
  expr: pulsecheck_monitor_certificate_expiry_timestamp_seconds - time() < 7 * 86400
```

## Administration

Administrators only.

| Method and path | Description |
|---|---|
| `GET` · `POST /api/users`, `PATCH` · `DELETE /api/users/{id}` | Manage accounts. |
| `DELETE /api/users/{id}/two-factor` | Reset someone's two-factor authentication after they lost their device. |
| `GET` · `POST /api/teams`, `GET` · `PATCH` · `DELETE /api/teams/{id}` | Manage teams. |
| `PUT /api/instance` | Instance name, time zone and language. |
| `GET` · `PUT` · `DELETE /api/settings/mail`, `POST …/test` | Mail service. |
| `GET /api/audit-log` | Audit log, 50 per page. Filters: `subject_type`, `action`, `user_id`, `from`, `to`. |

## Heartbeats and badges

These need no token; the secret is in the URL.

| Method and path | Description |
|---|---|
| `GET` · `POST` · `HEAD /api/heartbeat/{token}` | Record a heartbeat. Call it at the end of every run of your job. |
| `GET /api/public/badges/{token}/status.svg` | Current status badge. |
| `GET /api/public/badges/{token}/uptime.svg?days=30` | Uptime badge (`7`, `30` or `90` days). |

```sh
# crontab: back up every night and report to PulseCheck only on success
0 3 * * * /usr/local/bin/backup.sh && curl -fsS -m 10 --retry 3 https://status.example.com/api/heartbeat/AbC…
```
