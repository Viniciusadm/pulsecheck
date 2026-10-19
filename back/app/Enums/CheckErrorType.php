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
    case KeywordMissing = 'keyword_missing';
    case KeywordPresent = 'keyword_present';
    case Tls = 'tls';
    case CertificateExpired = 'certificate_expired';
    case HeartbeatMissed = 'heartbeat_missed';
    case Unknown = 'unknown';
}
