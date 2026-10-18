<?php

namespace App\Models;

use App\Enums\IncidentKind;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['kind', 'started_at', 'resolved_at', 'cause'])]
class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory;

    protected $attributes = [
        'kind' => 'outage',
    ];

    protected function casts(): array
    {
        return [
            'kind' => IncidentKind::class,
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    protected function isOpen(): Attribute
    {
        return Attribute::get(fn (): bool => $this->resolved_at === null);
    }

    protected function state(): Attribute
    {
        return Attribute::get(fn (): string => $this->is_open ? 'open' : 'resolved');
    }

    protected function durationSeconds(): Attribute
    {
        return Attribute::get(
            fn (): int => (int) $this->started_at->diffInSeconds($this->resolved_at ?? now())
        );
    }
}
