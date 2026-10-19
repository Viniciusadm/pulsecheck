<?php

namespace App\Enums;

enum MaintenanceWindowType: string
{
    case OneOff = 'one_off';
    case Recurring = 'recurring';
}
