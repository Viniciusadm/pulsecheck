<?php

namespace App\Models;

use App\Enums\NotificationChannelType;
use Database\Factories\NotificationChannelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['type', 'name', 'config', 'enabled', 'is_default'])]
#[Hidden(['config'])]
class NotificationChannel extends Model
{
    /** @use HasFactory<NotificationChannelFactory> */
    use HasFactory;

    protected $attributes = [
        'enabled' => true,
        'is_default' => false,
    ];

    protected function casts(): array
    {
        return [
            'type' => NotificationChannelType::class,
            'config' => 'encrypted:array',
            'enabled' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function monitors(): BelongsToMany
    {
        return $this->belongsToMany(Monitor::class);
    }

    public function setting(string $key): mixed
    {
        return $this->config[$key] ?? null;
    }
}
