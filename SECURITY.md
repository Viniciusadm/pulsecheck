# Security policy

## Reporting a vulnerability

Please report vulnerabilities privately through GitHub: open the repository's **Security** tab and choose **Report a vulnerability**. Do not open a public issue for security problems.

Include what you found, how to reproduce it and the impact you expect. You will get an acknowledgement, and the fix will be credited to you unless you prefer otherwise.

Issues of particular interest:

- Ways to make the server request internal addresses (SSRF) from checks or notification webhooks, including through redirects, DNS or URL parsing tricks.
- Access to another user's monitors, results, incidents or settings.
- Exposure of notification channel secrets (Telegram bot token, Slack or Discord webhook URLs, webhook headers), `APP_KEY` or other secrets in responses or logs.

## Supported versions

Only the latest version on the `master` branch receives security fixes.

## Scope notes

The README's [Security](README.md#security) section lists what PulseCheck protects against and its known limitations (for example, DNS changes between two checks). Enabling `PULSECHECK_ALLOW_PRIVATE_TARGETS` deliberately allows internal targets and is out of scope.
