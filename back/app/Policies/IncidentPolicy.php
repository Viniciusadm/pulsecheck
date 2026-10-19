<?php

namespace App\Policies;

use App\Models\Incident;
use App\Models\IncidentNote;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class IncidentPolicy
{
    public function view(User $user, Incident $incident): Response
    {
        return $incident->monitor->isVisibleTo($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function deleteNote(User $user, Incident $incident, IncidentNote $note): Response
    {
        $visibility = $this->view($user, $incident);

        if ($visibility->denied()) {
            return $visibility;
        }

        return $note->user_id === $user->id || $user->isAdmin()
            ? Response::allow()
            : Response::deny();
    }
}
