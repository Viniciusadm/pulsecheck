<?php

namespace App\Models;

use Database\Factories\InstanceSettingsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'timezone', 'locale'])]
class InstanceSettings extends Model
{
    /** @use HasFactory<InstanceSettingsFactory> */
    use HasFactory;

    public const CURRENT_BINDING = 'pulsecheck.instance_settings';

    public const DEFAULT_NAME = 'PulseCheck';

    protected $table = 'instance_settings';

    protected $attributes = [
        'locale' => 'en',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => static::forgetCurrent());
        static::deleted(fn () => static::forgetCurrent());
    }

    public static function current(): ?self
    {
        return app(self::CURRENT_BINDING);
    }

    public static function forgetCurrent(): void
    {
        app()->forgetInstance(self::CURRENT_BINDING);
    }

    public static function currentName(): string
    {
        return static::current()?->name ?? self::DEFAULT_NAME;
    }

    public static function currentTimezone(): string
    {
        return static::current()?->timezone ?? config('app.timezone') ?: 'UTC';
    }

    public static function currentLocale(): string
    {
        $locale = static::current()?->locale;

        return in_array($locale, config('pulsecheck.locales'), true)
            ? $locale
            : config('pulsecheck.default_locale');
    }
}
