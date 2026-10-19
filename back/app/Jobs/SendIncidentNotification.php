<?php

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Exceptions\NotificationDeliveryException;
use App\Models\Incident;
use App\Models\InstanceSettings;
use App\Notifications\AlertMessage;
use App\Notifications\AlertSeverity;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class SendIncidentNotification implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const QUEUE = 'notifications';

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct(
        public int $incidentId,
        public int $channelId,
        public NotificationType $type,
        public int $sequence = 0,
    ) {
        $this->onQueue(self::QUEUE);
    }

    public function uniqueId(): string
    {
        $key = "{$this->incidentId}:{$this->channelId}:{$this->type->value}";

        return $this->sequence > 0 ? "{$key}:{$this->sequence}" : $key;
    }

    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(): void
    {
        $incident = Incident::query()->with('monitor')->find($this->incidentId);
        $channel = $incident?->monitor->notificationChannels()->where('enabled', true)->find($this->channelId);

        if ($channel === null || ! $channel->receives($this->type)) {
            return;
        }

        if (in_array($this->type, [NotificationType::Down, NotificationType::Degraded, NotificationType::Reminder], true) && $incident->resolved_at !== null) {
            return;
        }

        if ($this->type === NotificationType::Reminder && ($incident->acknowledged_at !== null || $incident->monitor->is_paused || $incident->monitor->isInMaintenance())) {
            return;
        }

        App::setLocale(InstanceSettings::currentLocale());

        $message = AlertMessage::fromIncident($incident, $this->type)->formattedAs($channel->message_format);

        if ($channel->isQuietAt(now()) && ! ($channel->quiet_hours_allow_critical && $message->severity === AlertSeverity::Critical)) {
            return;
        }

        try {
            $channel->type->driver()->send($channel, $message);
        } catch (NotificationDeliveryException $exception) {
            $willRetry = $exception->isRetryable && $this->attempts() < $this->tries;

            Log::warning('Notification delivery failed.', [
                'incident_id' => $this->incidentId,
                'monitor_id' => $incident->monitor_id,
                'channel_id' => $channel->id,
                'channel_type' => $channel->type->value,
                'type' => $this->type->value,
                'reason' => $exception->getMessage(),
                'attempt' => $this->attempts(),
                'will_retry' => $willRetry,
            ]);

            if ($willRetry) {
                $this->release($this->backoff()[$this->attempts() - 1] ?? 120);
            }
        }
    }
}
