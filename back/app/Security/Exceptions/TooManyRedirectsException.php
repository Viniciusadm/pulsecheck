<?php

namespace App\Security\Exceptions;

use App\Enums\CheckErrorType;
use RuntimeException;

class TooManyRedirectsException extends RuntimeException
{
    public function __construct(int $maxRedirects)
    {
        parent::__construct("More than {$maxRedirects} redirects.");
    }

    public function errorType(): CheckErrorType
    {
        return CheckErrorType::TooManyRedirects;
    }
}
