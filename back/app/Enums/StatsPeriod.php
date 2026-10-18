<?php

namespace App\Enums;

enum StatsPeriod: string
{
    case Day = '24h';
    case Week = '7d';
    case Month = '30d';

    public function hours(): int
    {
        return match ($this) {
            self::Day => 24,
            self::Week => 24 * 7,
            self::Month => 24 * 30,
        };
    }

    public function bucketMinutes(): int
    {
        return match ($this) {
            self::Day => 15,
            self::Week => 60,
            self::Month => 360,
        };
    }
}
