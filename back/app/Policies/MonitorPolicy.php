<?php

namespace App\Policies;

use App\Models\Monitor;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MonitorPolicy
{
    public function view(User $user, Monitor $monitor): Response
    {
        return $monitor->isVisibleTo($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, Monitor $monitor): Response
    {
        $visibility = $this->view($user, $monitor);

        if ($visibility->denied()) {
            return $visibility;
        }

        return $user->canManageResources()
            ? Response::allow()
            : Response::deny();
    }
}
