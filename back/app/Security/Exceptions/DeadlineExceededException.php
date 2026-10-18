<?php

namespace App\Security\Exceptions;

use App\Enums\CheckErrorType;
use RuntimeException;

class DeadlineExceededException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The request did not finish within the timeout.');
    }

    public function errorType(): CheckErrorType
    {
        return CheckErrorType::Timeout;
    }
}
