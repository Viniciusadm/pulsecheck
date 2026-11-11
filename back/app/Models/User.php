<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\StatsPeriod;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'can_view_all_monitors'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_last_used_step'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $attributes = [
        'role' => 'member',
        'can_view_all_monitors' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'can_view_all_monitors' => 'boolean',
            'dashboard_layout' => 'array',
            'dashboard_period' => StatsPeriod::class,
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_last_used_step' => 'integer',
        ];
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function canViewEverything(): bool
    {
        return $this->isAdmin() || $this->can_view_all_monitors;
    }

    public function canManageResources(): bool
    {
        return $this->role === UserRole::Admin || $this->role === UserRole::Manager;
    }

    public function scopedTeamIds(array $teamIds): array
    {
        return $this->canViewEverything() ? $teamIds : array_values(array_intersect($teamIds, $this->teams->modelKeys()));
    }

    public function visibleTeamIds(): array
    {
        $teams = $this->canViewEverything() ? Team::query() : $this->teams();

        return $teams->pluck('teams.id')->all();
    }

    public function scopeWhoCanSee(Builder $query, Monitor $monitor): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('role', UserRole::Admin)
            ->orWhere('can_view_all_monitors', true)
            ->orWhereHas('teams', fn (Builder $teams) => $teams->whereIn('teams.id', $monitor->teams()->select('teams.id'))));
    }
}
