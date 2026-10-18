<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\MonitorIncidentClosed;
use App\Events\MonitorRecovered;
use App\Events\MonitorWentDown;
use App\Jobs\SendIncidentNotification;

class QueueIncidentNotifications
{
    public function handle(MonitorWentDown|MonitorRecovered|MonitorIncidentClosed $event): void
    {
        $type = match (true) {
            $event instanceof MonitorWentDown => NotificationType::Down,
            $event instanceof MonitorRecovered => NotificationType::Recovered,
            $event instanceof MonitorIncidentClosed => NotificationType::Closed,
        };

        $event->monitor->notificationChannels()
            ->where('enabled', true)
            ->pluck('notification_channels.id')
            ->each(fn (int $channelId) => SendIncidentNotification::dispatch($event->incident->id, $channelId, $type));
    }
}
