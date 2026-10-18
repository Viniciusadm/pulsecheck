<?php

namespace App\Enums;

enum Transition: string
{
    case BecameUp = 'became_up';
    case WentDown = 'went_down';
    case Recovered = 'recovered';
    case BecameDegraded = 'became_degraded';
    case DegradationResolved = 'degradation_resolved';
}
