<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateUser
{
    public function __construct(private SyncUserAccess $syncUserAccess) {}

    public function handle(User $user, array $attributes): User
    {
        return DB::transaction(function () use ($user, $attributes): User {
            $adminIds = User::query()->where('role', UserRole::Admin)->orderBy('id')->lockForUpdate()->pluck('id');
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $user->fill(Arr::except($attributes, 'team_ids'));

            if ($user->isDirty('role') && $adminIds->count() === 1 && $adminIds->contains($user->id)) {
                throw ValidationException::withMessages([
                    'role' => __('The last administrator cannot be demoted.'),
                ]);
            }

            $this->syncUserAccess->handle($user, $attributes['team_ids'] ?? null);

            return $user;
        });
    }
}
