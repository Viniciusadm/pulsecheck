<?php

namespace App\Notifications;

enum AlertSeverity: string
{
    case Critical = 'critical';
    case Resolved = 'resolved';
    case Info = 'info';
}
