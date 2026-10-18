<?php

namespace App\Actions;

use App\Models\User;

class SyncUserAccess
{
    public function handle(User $user, ?array $teamIds): void
    {
        if ($user->isAdmin()) {
            $user->can_view_all_monitors = false;
            $teamIds = [];
        }

        $user->save();

        if ($teamIds !== null) {
            $user->teams()->sync($teamIds);
        }
    }
}
