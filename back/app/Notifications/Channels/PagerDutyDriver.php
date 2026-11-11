<?php

namespace App\Notifications\Channels;

use App\Models\InstanceSettings;
use App\Models\NotificationChannel;
use App\Notifications\AlertMessage;
use App\Notifications\AlertSeverity;
use Illuminate\Support\Str;

class PagerDutyDriver extends HttpChannelDriver
{
    public const EVENTS_URL = 'https://events.pagerduty.com/v2/enqueue';

    public const ROUTING_KEY_PATTERN = '/^[A-Za-z0-9]{32}$/D';

    private const RESOLVING_EVENTS = ['monitor.recovered', 'monitor.degradation_resolved', 'monitor.incident_closed'];

    public function rules(): array
    {
        return [
            'routing_key' => ['required', 'string', 'regex:'.self::ROUTING_KEY_PATTERN],
        ];
    }

    public function secretKeys(): array
    {
        return ['routing_key'];
    }

    public function send(NotificationChannel $channel, AlertMessage $message): void
    {
        $dedupKey = $message->incident === null ? 'pulsecheck-test-'.Str::uuid() : 'pulsecheck-incident-'.$message->incident->id;
        $routingKey = $channel->setting('routing_key');

        if (in_array($message->event, self::RESOLVING_EVENTS, true)) {
            $this->post(self::EVENTS_URL, ['routing_key' => $routingKey, 'event_action' => 'resolve', 'dedup_key' => $dedupKey]);

            return;
        }

        $monitor = $message->incident?->monitor;

        $this->post(self::EVENTS_URL, [
            'routing_key' => $routingKey,
            'event_action' => 'trigger',
            'dedup_key' => $dedupKey,
            'payload' => [
                'summary' => Str::limit($message->title, 1000),
                'source' => $monitor?->displayTarget() ?? InstanceSettings::currentName(),
                'severity' => $this->severity($message->severity),
                'timestamp' => $message->occurredAt->copy()->utc()->toIso8601ZuluString(),
                'component' => $monitor?->name,
                'group' => InstanceSettings::currentName(),
                'class' => $message->event,
                'custom_details' => $message->fields === [] ? ['summary' => $message->summary] : $message->fields,
            ],
            'client' => 'PulseCheck',
        ]);

        if ($message->incident === null) {
            $this->post(self::EVENTS_URL, ['routing_key' => $routingKey, 'event_action' => 'resolve', 'dedup_key' => $dedupKey]);
        }
    }

    private function severity(AlertSeverity $severity): string
    {
        return match ($severity) {
            AlertSeverity::Critical => 'critical',
            AlertSeverity::Warning => 'warning',
            AlertSeverity::Resolved, AlertSeverity::Info => 'info',
        };
    }
}
