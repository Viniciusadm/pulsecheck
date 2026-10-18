<?php

namespace App\Models;

use App\Enums\CheckErrorType;
use Database\Factories\CheckResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['started_at', 'finished_at', 'latency_ms', 'status_code', 'is_success', 'error_type', 'error_message'])]
#[WithoutTimestamps]
class CheckResult extends Model
{
    /** @use HasFactory<CheckResultFactory> */
    use HasFactory, MassPrunable;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'latency_ms' => 'integer',
            'status_code' => 'integer',
            'is_success' => 'boolean',
            'error_type' => CheckErrorType::class,
        ];
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    public function prunable(): Builder
    {
        return static::query()->where('started_at', '<', now()->subDays((int) config('pulsecheck.history_retention_days')));
    }
}
