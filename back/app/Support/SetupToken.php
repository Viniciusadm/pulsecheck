<?php

namespace App\Support;

use App\Models\InstanceSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SetupToken
{
    private const CACHE_KEY = 'pulsecheck:generated-setup-token';

    public static function current(): ?string
    {
        $configured = config('pulsecheck.setup_token');

        if (filled($configured)) {
            return (string) $configured;
        }

        if (! config('pulsecheck.generate_setup_token') || InstanceSettings::current() !== null) {
            return null;
        }

        return Cache::rememberForever(self::CACHE_KEY, fn (): string => Str::random(32));
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
