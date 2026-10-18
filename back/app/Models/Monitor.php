<?php

namespace App\Models;

use App\Enums\MonitorStatus;
use Database\Factories\MonitorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'url', 'interval_minutes', 'timeout_seconds'])]
class Monitor extends Model
{
    /** @use HasFactory<MonitorFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'pending',
        'is_paused' => false,
        'consecutive_failures' => 0,
        'consecutive_successes' => 0,
    ];

    protected function casts(): array
    {
        return [
            'interval_minutes' => 'integer',
            'timeout_seconds' => 'integer',
            'status' => MonitorStatus::class,
            'is_paused' => 'boolean',
            'consecutive_failures' => 'integer',
            'consecutive_successes' => 'integer',
            'last_status_code' => 'integer',
            'last_latency_ms' => 'integer',
            'last_checked_at' => 'datetime',
            'last_failed_at' => 'datetime',
            'next_check_at' => 'datetime',
        ];
    }

    public function checkResults(): HasMany
    {
        return $this->hasMany(CheckResult::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function notificationChannels(): BelongsToMany
    {
        return $this->belongsToMany(NotificationChannel::class);
    }
}
