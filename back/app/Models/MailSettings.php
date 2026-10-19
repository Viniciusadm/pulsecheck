<?php

namespace App\Models;

use App\Enums\MailDriver;
use App\Mail\InstanceMailConfigurator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['driver', 'from_address', 'from_name', 'config'])]
#[Hidden(['config'])]
class MailSettings extends Model
{
    public const CURRENT_BINDING = 'pulsecheck.mail_settings';

    protected $table = 'mail_settings';

    protected function casts(): array
    {
        return [
            'driver' => MailDriver::class,
            'config' => 'encrypted:array',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::refreshMailer());
        static::deleted(fn () => static::refreshMailer());
    }

    public static function current(): ?self
    {
        return app(self::CURRENT_BINDING);
    }

    public static function forgetCurrent(): void
    {
        app()->forgetInstance(self::CURRENT_BINDING);
    }

    private static function refreshMailer(): void
    {
        static::forgetCurrent();

        if (app()->resolved('mail.manager')) {
            app(InstanceMailConfigurator::class)->apply(app('mail.manager'));
        }
    }
}
