<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\MonitorRecovered;
use App\Events\MonitorWentDown;
use App\Jobs\SendTelegramNotification;

class QueueTelegramNotification
{
    public function handle(MonitorWentDown|MonitorRecovered $event): void
    {
        if (! $event->monitor->user->notificationSetting?->isTelegramReady()) {
            return;
        }

        $type = $event instanceof MonitorWentDown ? NotificationType::Down : NotificationType::Recovered;

        SendTelegramNotification::dispatch($event->incident->id, $type);
    }
}
