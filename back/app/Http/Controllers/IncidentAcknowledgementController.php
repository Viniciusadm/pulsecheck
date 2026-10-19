<?php

namespace App\Http\Controllers;

use App\Actions\AcknowledgeIncident;
use App\Http\Resources\IncidentResource;
use App\Models\Incident;
use Illuminate\Http\Request;

class IncidentAcknowledgementController extends Controller
{
    public function __invoke(Request $request, Incident $incident, AcknowledgeIncident $acknowledgeIncident): IncidentResource
    {
        $incident = $acknowledgeIncident->handle($request->user(), $incident);

        return new IncidentResource($incident->load(['monitor:id,name', 'acknowledger:id,name'])->loadCount('notes'));
    }
}
