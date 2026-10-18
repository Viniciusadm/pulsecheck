<?php

namespace App\Security\Exceptions;

use App\Enums\CheckErrorType;
use RuntimeException;

class UnresolvableHostException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The host could not be resolved.');
    }

    public function errorType(): CheckErrorType
    {
        return CheckErrorType::Dns;
    }
}
