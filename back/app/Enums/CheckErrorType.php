<?php

namespace App\Enums;

enum CheckErrorType: string
{
    case Timeout = 'timeout';
    case Dns = 'dns';
    case Connection = 'connection';
    case HttpStatus = 'http_status';
    case BlockedDestination = 'blocked_destination';
    case TooManyRedirects = 'too_many_redirects';
    case Unknown = 'unknown';
}
