<?php

namespace App\Enums;

use App\Services\Checkers\HttpChecker;
use App\Services\Checkers\MonitorChecker;

enum MonitorType: string
{
    case Http = 'http';

    public function checker(): MonitorChecker
    {
        return app(match ($this) {
            self::Http => HttpChecker::class,
        });
    }
}
