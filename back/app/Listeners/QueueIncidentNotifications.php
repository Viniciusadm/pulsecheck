<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\MonitorRecovered;
use App\Events\MonitorWentDown;
use App\Jobs\SendIncidentNotification;

class QueueIncidentNotifications
{
    public function handle(MonitorWentDown|MonitorRecovered $event): void
    {
        $type = $event instanceof MonitorWentDown ? NotificationType::Down : NotificationType::Recovered;

        $event->monitor->notificationChannels()
            ->where('enabled', true)
            ->pluck('notification_channels.id')
            ->each(fn (int $channelId) => SendIncidentNotification::dispatch($event->incident->id, $channelId, $type));
    }
}
