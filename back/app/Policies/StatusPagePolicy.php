<?php

namespace App\Policies;

use App\Models\StatusPage;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class StatusPagePolicy
{
    public function view(User $user, StatusPage $statusPage): Response
    {
        return $statusPage->isVisibleTo($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, StatusPage $statusPage): Response
    {
        $visibility = $this->view($user, $statusPage);

        if ($visibility->denied()) {
            return $visibility;
        }

        return $user->canManageResources()
            ? Response::allow()
            : Response::deny();
    }
}
