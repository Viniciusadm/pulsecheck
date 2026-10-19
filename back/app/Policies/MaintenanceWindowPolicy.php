<?php

namespace App\Policies;

use App\Models\MaintenanceWindow;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MaintenanceWindowPolicy
{
    public function view(User $user, MaintenanceWindow $window): Response
    {
        return $window->isVisibleTo($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, MaintenanceWindow $window): Response
    {
        $visibility = $this->view($user, $window);

        if ($visibility->denied()) {
            return $visibility;
        }

        return $user->canManageResources()
            ? Response::allow()
            : Response::deny();
    }
}
