<?php

namespace App\Http\Controllers;

use App\Actions\CreateIncidentNote;
use App\Events\IncidentUpdated;
use App\Http\Requests\StoreIncidentNoteRequest;
use App\Http\Resources\IncidentNoteResource;
use App\Models\Incident;
use App\Models\IncidentNote;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class IncidentNoteController extends Controller
{
    public function index(Incident $incident): AnonymousResourceCollection
    {
        return IncidentNoteResource::collection($incident->notes()->with('user:id,name')->oldest()->oldest('id')->get());
    }

    public function store(StoreIncidentNoteRequest $request, Incident $incident, CreateIncidentNote $createIncidentNote): IncidentNoteResource
    {
        return new IncidentNoteResource($createIncidentNote->handle($request->user(), $incident, $request->validated('body'))->load('user:id,name'));
    }

    public function destroy(Incident $incident, IncidentNote $note): Response
    {
        Gate::authorize('deleteNote', [$incident, $note]);

        $note->delete();

        IncidentUpdated::dispatch($incident);

        return response()->noContent();
    }
}
