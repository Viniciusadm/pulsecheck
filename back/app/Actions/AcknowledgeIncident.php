<?php

namespace App\Actions;

use App\Events\IncidentUpdated;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcknowledgeIncident
{
    public function handle(User $user, Incident $incident): Incident
    {
        return DB::transaction(function () use ($user, $incident): Incident {
            $incident = Incident::query()->lockForUpdate()->findOrFail($incident->id);

            if ($incident->resolved_at !== null) {
                throw ValidationException::withMessages(['incident' => __('Resolved incidents cannot be acknowledged.')]);
            }

            if ($incident->acknowledged_at !== null) {
                return $incident;
            }

            $incident->forceFill(['acknowledged_at' => now(), 'acknowledged_by' => $user->id])->save();

            IncidentUpdated::dispatch($incident);

            return $incident;
        });
    }
}
