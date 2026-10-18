<?php

namespace App\Enums;

enum IncidentKind: string
{
    case Outage = 'outage';
    case Degradation = 'degradation';
}
