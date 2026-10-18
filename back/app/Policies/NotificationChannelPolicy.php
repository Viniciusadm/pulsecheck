<?php

namespace App\Policies;

use App\Models\NotificationChannel;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class NotificationChannelPolicy
{
    public function update(User $user, NotificationChannel $notificationChannel): Response
    {
        return $this->ownership($user, $notificationChannel);
    }

    public function delete(User $user, NotificationChannel $notificationChannel): Response
    {
        return $this->ownership($user, $notificationChannel);
    }

    public function test(User $user, NotificationChannel $notificationChannel): Response
    {
        return $this->ownership($user, $notificationChannel);
    }

    private function ownership(User $user, NotificationChannel $notificationChannel): Response
    {
        return $notificationChannel->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
