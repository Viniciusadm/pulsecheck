<?php

namespace App\Enums;

enum NotificationType: string
{
    case Down = 'down';
    case Recovered = 'recovered';
    case Closed = 'closed';
    case Degraded = 'degraded';
    case DegradationResolved = 'degradation_resolved';
}
