<?php

namespace App\Actions;

use App\Events\IncidentUpdated;
use App\Models\Incident;
use App\Models\IncidentNote;
use App\Models\User;

class CreateIncidentNote
{
    public function handle(User $user, Incident $incident, string $body): IncidentNote
    {
        $note = $incident->notes()->make(['body' => $body]);
        $note->user()->associate($user);
        $note->save();

        IncidentUpdated::dispatch($incident);

        return $note;
    }
}
