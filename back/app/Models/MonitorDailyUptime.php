<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[WithoutTimestamps]
class MonitorDailyUptime extends Model
{
    use MassPrunable;

    public $incrementing = false;

    protected $primaryKey = null;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'total_checks' => 'integer',
            'successful_checks' => 'integer',
            'latency_sum_ms' => 'integer',
            'latency_samples' => 'integer',
        ];
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    public function prunable(): Builder
    {
        return static::query()->where('date', '<', now('UTC')->subDays((int) config('pulsecheck.uptime_rollup_retention_days'))->toDateString());
    }
}
