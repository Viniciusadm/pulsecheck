<?php

namespace App\Security\Exceptions;

use App\Enums\CheckErrorType;
use App\Security\UnsafeUrlReason;
use RuntimeException;

class UnsafeUrlException extends RuntimeException
{
    public function __construct(public readonly UnsafeUrlReason $reason)
    {
        parent::__construct('Blocked destination: '.$reason->value);
    }

    public function errorType(): CheckErrorType
    {
        return CheckErrorType::BlockedDestination;
    }
}
