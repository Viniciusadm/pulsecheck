<?php

namespace App\Notifications;

enum AlertSeverity: string
{
    case Critical = 'critical';
    case Warning = 'warning';
    case Resolved = 'resolved';
    case Info = 'info';
}
