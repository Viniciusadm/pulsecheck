<?php

namespace App\Models;

use App\Enums\MonitorStatus;
use App\Enums\MonitorType;
use App\Models\Concerns\BelongsToTeams;
use Database\Factories\MonitorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'type', 'url', 'options', 'secrets', 'interval_minutes', 'timeout_seconds', 'latency_threshold_ms'])]
#[Hidden(['secrets'])]
class Monitor extends Model
{
    /** @use HasFactory<MonitorFactory> */
    use BelongsToTeams, HasFactory;

    protected $attributes = [
        'type' => 'http',
        'options' => '{}',
        'status' => 'pending',
        'is_paused' => false,
        'consecutive_failures' => 0,
        'consecutive_successes' => 0,
        'consecutive_degraded' => 0,
        'consecutive_healthy' => 0,
        'config_revision' => 1,
    ];

    protected function casts(): array
    {
        return [
            'type' => MonitorType::class,
            'options' => 'array',
            'secrets' => 'encrypted:array',
            'interval_minutes' => 'integer',
            'timeout_seconds' => 'integer',
            'latency_threshold_ms' => 'integer',
            'config_revision' => 'integer',
            'status' => MonitorStatus::class,
            'is_paused' => 'boolean',
            'consecutive_failures' => 'integer',
            'consecutive_successes' => 'integer',
            'consecutive_degraded' => 'integer',
            'consecutive_healthy' => 'integer',
            'last_status_code' => 'integer',
            'last_latency_ms' => 'integer',
            'last_checked_at' => 'datetime',
            'last_failed_at' => 'datetime',
            'next_check_at' => 'datetime',
        ];
    }

    public function option(string $key): mixed
    {
        return array_key_exists($key, $this->options ?? [])
            ? $this->options[$key]
            : ($this->type->checker()->defaultOptions()[$key] ?? null);
    }

    public function secret(string $key): mixed
    {
        return $this->secrets[$key] ?? null;
    }

    public function checkResults(): HasMany
    {
        return $this->hasMany(CheckResult::class);
    }

    public function dailyUptimes(): HasMany
    {
        return $this->hasMany(MonitorDailyUptime::class);
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
