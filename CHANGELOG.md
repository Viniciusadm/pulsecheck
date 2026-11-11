# Changelog

All notable changes are listed here. Versions follow [Semantic Versioning](https://semver.org/); each `vX.Y.Z` tag publishes `ghcr.io/viniciusadm/pulsecheck-app` and `pulsecheck-web` images.

## Unreleased

### Added

- **My account** page: change your name, email and password (other sessions are logged out) and choose a light, dark or system theme.
- **Dark mode** across the app, the login screens and public status pages.
- **Two-factor authentication** (TOTP) with recovery codes, protection against code reuse, administrator reset from the **Users** page and `php artisan pulsecheck:reset-two-factor` for a locked-out administrator.
- **Personal API tokens** with read-only or read-and-write access and optional expiry, plus the [API reference](docs/API.md).
- **Prometheus endpoint** at `/api/metrics`.
- **Audit log** of changes, logins and security events (`PULSECHECK_AUDIT_LOG_RETENTION_DAYS`, 365 days by default).
- Notification channels for **Microsoft Teams, Google Chat, PagerDuty, Pushover and ntfy**.
- **Search** and **bulk pause, resume and delete** on the monitor list.
- Keyword, TCP port, SSL certificate and heartbeat monitors; degraded state by response time; advanced HTTP options.
- Incident acknowledgement, notes and repeated alerts; one-off and weekly maintenance windows.
- Public status pages and uptime badges, with an optional custom domain per page that serves only that page.
- Per-channel events, quiet hours, message format and live preview; mail service configured from the panel.
- Teams with per-team monitors and channels, roles (administrator, manager, member) and a first-run setup wizard.
- Customizable dashboard and real-time updates over WebSockets (Reverb).
- English and Portuguese (Brazil) interface and alerts.

### Changed

- PulseCheck is now one instance per organization: public sign-up was replaced by a first-run setup wizard, and administrators create accounts and teams. Monitors and channels belong to teams instead of individual users.
- Dependencies updated, including security fixes in Symfony components and `follow-redirects`.

## 1.0.0 — 2026-10-18

First public release: HTTP(S) monitors, incidents, uptime and response time metrics, Telegram alerts, SSRF protection and a single `docker compose up` deployment.
