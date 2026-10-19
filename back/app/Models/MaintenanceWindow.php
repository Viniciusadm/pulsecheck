<?php

namespace App\Models;

use App\Enums\MaintenanceWindowType;
use Carbon\CarbonInterface;
use Database\Factories\MaintenanceWindowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'type', 'starts_at', 'ends_at', 'weekdays', 'start_time', 'end_time', 'timezone'])]
class MaintenanceWindow extends Model
{
    /** @use HasFactory<MaintenanceWindowFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => MaintenanceWindowType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'weekdays' => 'array',
        ];
    }

    public function monitors(): BelongsToMany
    {
        return $this->belongsToMany(Monitor::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->canViewEverything()) {
            return;
        }

        $query->where(fn (Builder $windows) => $windows
            ->whereHas('teams', fn (Builder $teams) => $teams->whereIn('teams.id', $user->teams()->select('teams.id')))
            ->orWhereHas('monitors', fn (Builder $monitors) => $monitors->visibleTo($user)));
    }

    public function scopeAffecting(Builder $query, Monitor $monitor): void
    {
        $query->where(fn (Builder $windows) => $windows
            ->whereHas('monitors', fn (Builder $monitors) => $monitors->whereKey($monitor->id))
            ->orWhereHas('teams', fn (Builder $teams) => $teams->whereIn('teams.id', $monitor->teams()->select('teams.id'))));
    }

    public function scopeNotFinished(Builder $query, ?CarbonInterface $at = null): void
    {
        $query->where(fn (Builder $windows) => $windows
            ->where('type', MaintenanceWindowType::Recurring)
            ->orWhere('ends_at', '>', $at ?? now()));
    }

    public function isVisibleTo(User $user): bool
    {
        return static::query()->visibleTo($user)->whereKey($this->id)->exists();
    }

    public function isManageableBy(User $user): bool
    {
        if (! $user->canManageResources()) {
            return false;
        }

        if ($user->canViewEverything()) {
            return true;
        }

        $this->loadMissing(['teams:id', 'monitors:id', 'monitors.teams:id']);

        return array_diff($this->teams->modelKeys(), $user->teams->modelKeys()) === []
            && $this->monitors->every(fn (Monitor $monitor) => $monitor->isVisibleTo($user));
    }
}
