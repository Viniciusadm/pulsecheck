<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Incident;
use App\Models\InstanceSettings;
use Carbon\CarbonInterface;

final readonly class AlertMessage
{
    public const PAYLOAD_VERSION = 1;

    public function __construct(
        public string $event,
        public AlertSeverity $severity,
        public string $title,
        public ?string $summary,
        public array $fields,
        public CarbonInterface $occurredAt,
        public ?Incident $incident = null,
    ) {}

    public static function fromIncident(Incident $incident, NotificationType $type): self
    {
        $monitor = $incident->monitor;
        $host = self::host($monitor->url);

        return match ($type) {
            NotificationType::Down => new self(
                event: 'monitor.down',
                severity: AlertSeverity::Critical,
                title: '🔴 '.__(':monitor is DOWN', ['monitor' => $monitor->name]),
                summary: null,
                fields: [
                    __('Host') => $host,
                    __('Cause') => (string) $incident->cause,
                    __('Since') => self::formatTime($incident->started_at),
                ],
                occurredAt: $incident->started_at,
                incident: $incident,
            ),
            NotificationType::Recovered => new self(
                event: 'monitor.recovered',
                severity: AlertSeverity::Resolved,
                title: '🟢 '.__(':monitor is back UP', ['monitor' => $monitor->name]),
                summary: null,
                fields: [
                    __('Host') => $host,
                    __('Downtime') => self::formatDuration($incident->duration_seconds),
                    __('Resolved') => self::formatTime($incident->resolved_at ?? now()),
                ],
                occurredAt: $incident->resolved_at ?? now(),
                incident: $incident,
            ),
            NotificationType::Closed => new self(
                event: 'monitor.incident_closed',
                severity: AlertSeverity::Resolved,
                title: '⚪ '.__(':monitor incident closed', ['monitor' => $monitor->name]),
                summary: __('The monitor check settings were changed, so the open incident was closed. The monitor is checked from scratch.'),
                fields: [
                    __('Host') => $host,
                    __('Downtime') => self::formatDuration($incident->duration_seconds),
                    __('Closed') => self::formatTime($incident->resolved_at ?? now()),
                ],
                occurredAt: $incident->resolved_at ?? now(),
                incident: $incident,
            ),
            NotificationType::Degraded => new self(
                event: 'monitor.degraded',
                severity: AlertSeverity::Warning,
                title: '🟡 '.__(':monitor is DEGRADED', ['monitor' => $monitor->name]),
                summary: null,
                fields: [
                    __('Host') => $host,
                    __('Reason') => (string) $incident->cause,
                    __('Since') => self::formatTime($incident->started_at),
                ],
                occurredAt: $incident->started_at,
                incident: $incident,
            ),
            NotificationType::DegradationResolved => new self(
                event: 'monitor.degradation_resolved',
                severity: AlertSeverity::Resolved,
                title: '🟢 '.__(':monitor is performing normally again', ['monitor' => $monitor->name]),
                summary: null,
                fields: [
                    __('Host') => $host,
                    __('Duration') => self::formatDuration($incident->duration_seconds),
                    __('Resolved') => self::formatTime($incident->resolved_at ?? now()),
                ],
                occurredAt: $incident->resolved_at ?? now(),
                incident: $incident,
            ),
        };
    }

    public static function test(): self
    {
        return new self(
            event: 'test',
            severity: AlertSeverity::Info,
            title: '✅ '.__(':instance test message', ['instance' => InstanceSettings::currentName()]),
            summary: __('Down, recovery and degradation alerts for your monitors will arrive here.'),
            fields: [],
            occurredAt: now(),
        );
    }

    public function toText(): string
    {
        $lines = [$this->title];

        if ($this->summary !== null) {
            $lines[] = $this->summary;
        }

        foreach ($this->fields as $label => $value) {
            $lines[] = "{$label}: {$value}";
        }

        return implode("\n", $lines);
    }

    public function toPayload(): array
    {
        $monitor = $this->incident?->monitor;

        return [
            'version' => self::PAYLOAD_VERSION,
            'event' => $this->event,
            'severity' => $this->severity->value,
            'title' => $this->title,
            'text' => $this->toText(),
            'occurred_at' => self::isoTime($this->occurredAt),
            'instance' => [
                'name' => InstanceSettings::currentName(),
            ],
            'monitor' => $monitor === null ? null : [
                'id' => $monitor->id,
                'name' => $monitor->name,
                'host' => self::host($monitor->url),
            ],
            'incident' => $this->incident === null ? null : [
                'id' => $this->incident->id,
                'kind' => $this->incident->kind->value,
                'cause' => $this->incident->cause,
                'started_at' => self::isoTime($this->incident->started_at),
                'resolved_at' => $this->incident->resolved_at === null ? null : self::isoTime($this->incident->resolved_at),
                'duration_seconds' => $this->incident->resolved_at === null ? null : $this->incident->duration_seconds,
            ],
        ];
    }

    private static function host(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?? '';
        $port = parse_url($url, PHP_URL_PORT);

        return $port === null ? $host : "{$host}:{$port}";
    }

    private static function formatTime(CarbonInterface $moment): string
    {
        return $moment->copy()->setTimezone(InstanceSettings::currentTimezone())->format('Y-m-d H:i:s T');
    }

    private static function isoTime(CarbonInterface $moment): string
    {
        return $moment->copy()->utc()->toIso8601ZuluString();
    }

    private static function formatDuration(int $seconds): string
    {
        $parts = array_filter([
            'h' => intdiv($seconds, 3600),
            'm' => intdiv($seconds % 3600, 60),
            's' => $seconds % 60,
        ]);

        if ($parts === []) {
            return '0s';
        }

        return implode(' ', array_map(fn (int $value, string $unit) => $value.$unit, $parts, array_keys($parts)));
    }
}
