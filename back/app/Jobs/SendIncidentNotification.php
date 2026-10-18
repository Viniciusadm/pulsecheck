<?php

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Exceptions\NotificationDeliveryException;
use App\Models\Incident;
use App\Models\InstanceSettings;
use App\Notifications\AlertMessage;
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
    ) {
        $this->onQueue(self::QUEUE);
    }

    public function uniqueId(): string
    {
        return "{$this->incidentId}:{$this->channelId}:{$this->type->value}";
    }

    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(): void
    {
        $incident = Incident::query()->with('monitor')->find($this->incidentId);
        $channel = $incident?->monitor->notificationChannels()->where('enabled', true)->find($this->channelId);

        if ($channel === null) {
            return;
        }

        if ($this->type === NotificationType::Down && $incident->resolved_at !== null) {
            return;
        }

        App::setLocale(InstanceSettings::currentLocale());

        try {
            $channel->type->driver()->send($channel, AlertMessage::fromIncident($incident, $this->type));
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
