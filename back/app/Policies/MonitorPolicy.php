<?php

namespace App\Policies;

use App\Models\Monitor;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MonitorPolicy
{
    public function view(User $user, Monitor $monitor): Response
    {
        return $this->ownership($user, $monitor);
    }

    public function update(User $user, Monitor $monitor): Response
    {
        return $this->ownership($user, $monitor);
    }

    public function delete(User $user, Monitor $monitor): Response
    {
        return $this->ownership($user, $monitor);
    }

    public function pause(User $user, Monitor $monitor): Response
    {
        return $this->ownership($user, $monitor);
    }

    public function resume(User $user, Monitor $monitor): Response
    {
        return $this->ownership($user, $monitor);
    }

    private function ownership(User $user, Monitor $monitor): Response
    {
        return $monitor->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
