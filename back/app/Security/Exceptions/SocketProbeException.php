<?php

namespace App\Security\Exceptions;

use RuntimeException;

class SocketProbeException extends RuntimeException
{
    public const TIMEOUT = 'timeout';

    public const REFUSED = 'refused';

    public const TLS = 'tls';

    public const OTHER = 'other';

    public function __construct(public readonly string $kind, string $message)
    {
        parent::__construct($message);
    }
}
