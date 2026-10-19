<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[WithoutTimestamps]
class MonitorHourlyStat extends Model
{
    use MassPrunable;

    public $incrementing = false;

    protected $primaryKey = null;

    protected function casts(): array
    {
        return [
            'hour' => 'immutable_datetime',
            'checks' => 'integer',
            'successful_checks' => 'integer',
            'latency_sum_ms' => 'integer',
            'latency_samples' => 'integer',
            'p95_latency_ms' => 'integer',
        ];
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    public function prunable(): Builder
    {
        return static::query()->where('hour', '<', now('UTC')->subDays((int) config('pulsecheck.history_retention_days') + 1));
    }
}
