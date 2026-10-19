<?php

namespace App\Models;

use App\Enums\MonitorStatus;
use App\Enums\MonitorType;
use App\Models\Concerns\BelongsToTeams;
use Carbon\CarbonInterface;
use Database\Factories\MonitorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'type', 'url', 'options', 'secrets', 'interval_minutes', 'timeout_seconds', 'latency_threshold_ms', 'reminder_interval_minutes'])]
#[Hidden(['secrets', 'heartbeat_token', 'badge_token'])]
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
            'reminder_interval_minutes' => 'integer',
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
            'last_heartbeat_at' => 'datetime',
            'certificate_expires_at' => 'datetime',
            'maintenance_ends_at' => 'datetime',
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

    public function initialCheckAt(): CarbonInterface
    {
        return $this->type->isPassive()
            ? now()->addMinutes($this->interval_minutes)->addSeconds((int) $this->option('grace_seconds'))
            : now();
    }

    public function displayTarget(): string
    {
        return match (true) {
            $this->type === MonitorType::Heartbeat => __('Heartbeat'),
            $this->type === MonitorType::Tcp => $this->option('host').':'.$this->option('port'),
            default => self::hostOf((string) $this->url),
        };
    }

    public static function hostOf(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?? '';
        $port = parse_url($url, PHP_URL_PORT);

        return $port === null ? $host : "{$host}:{$port}";
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

    public function maintenanceWindows(): BelongsToMany
    {
        return $this->belongsToMany(MaintenanceWindow::class);
    }

    public function isInMaintenance(): bool
    {
        return $this->maintenance_ends_at !== null;
    }

    public function notificationChannels(): BelongsToMany
    {
        return $this->belongsToMany(NotificationChannel::class);
    }
}
