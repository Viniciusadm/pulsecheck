<?php

namespace App\Models\Concerns;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait BelongsToTeams
{
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->canViewEverything()) {
            return;
        }

        $query->whereHas('teams', fn (Builder $teams) => $teams->whereIn('teams.id', $user->teams()->select('teams.id')));
    }

    public function isVisibleTo(User $user): bool
    {
        if ($user->canViewEverything()) {
            return true;
        }

        if ($this->relationLoaded('teams')) {
            return array_intersect($this->teams->modelKeys(), $user->teams->modelKeys()) !== [];
        }

        return $this->teams()->whereIn('teams.id', $user->teams()->select('teams.id'))->exists();
    }
}
