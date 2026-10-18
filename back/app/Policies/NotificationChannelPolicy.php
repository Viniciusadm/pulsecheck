<?php

namespace App\Policies;

use App\Models\NotificationChannel;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class NotificationChannelPolicy
{
    public function view(User $user, NotificationChannel $channel): Response
    {
        return $channel->isVisibleTo($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, NotificationChannel $channel): Response
    {
        $visibility = $this->view($user, $channel);

        if ($visibility->denied()) {
            return $visibility;
        }

        return $user->canManageResources()
            ? Response::allow()
            : Response::deny();
    }
}
