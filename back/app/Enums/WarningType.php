<?php

namespace App\Enums;

enum WarningType: string
{
    case SlowResponse = 'slow_response';
    case CertificateExpiring = 'certificate_expiring';
}
