<?php

namespace App\Enums;

use App\Services\Checkers\HeartbeatChecker;
use App\Services\Checkers\HttpChecker;
use App\Services\Checkers\KeywordChecker;
use App\Services\Checkers\MonitorChecker;
use App\Services\Checkers\SslCertificateChecker;
use App\Services\Checkers\TcpChecker;

enum MonitorType: string
{
    case Http = 'http';
    case Keyword = 'keyword';
    case Ssl = 'ssl';
    case Tcp = 'tcp';
    case Heartbeat = 'heartbeat';

    public function checker(): MonitorChecker
    {
        return app(match ($this) {
            self::Http => HttpChecker::class,
            self::Keyword => KeywordChecker::class,
            self::Ssl => SslCertificateChecker::class,
            self::Tcp => TcpChecker::class,
            self::Heartbeat => HeartbeatChecker::class,
        });
    }

    public function isPassive(): bool
    {
        return $this === self::Heartbeat;
    }

    public function usesUrl(): bool
    {
        return in_array($this, [self::Http, self::Keyword, self::Ssl], true);
    }

    public function allowedIntervals(): array
    {
        return $this->isPassive() ? config('pulsecheck.heartbeat_allowed_intervals') : config('pulsecheck.allowed_intervals');
    }
}
