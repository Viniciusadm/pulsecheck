<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteUser
{
    public function handle(User $actor, User $user): void
    {
        if ($actor->is($user)) {
            throw ValidationException::withMessages([
                'user' => __('You cannot delete your own account.'),
            ]);
        }

        DB::transaction(function () use ($user): void {
            $adminIds = User::query()->where('role', UserRole::Admin)->orderBy('id')->lockForUpdate()->pluck('id');
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($adminIds->count() === 1 && $adminIds->contains($user->id)) {
                throw ValidationException::withMessages([
                    'user' => __('The last administrator cannot be deleted.'),
                ]);
            }

            $user->delete();
        });
    }
}
