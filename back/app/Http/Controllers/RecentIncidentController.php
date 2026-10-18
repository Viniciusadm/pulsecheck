<?php

namespace App\Http\Controllers;

use App\Http\Resources\IncidentResource;
use App\Models\Incident;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RecentIncidentController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'limit' => ['sometimes', 'integer', 'between:1,50'],
        ]);

        $incidents = Incident::query()
            ->with('monitor:id,name')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit($validated['limit'] ?? 10)
            ->get();

        return IncidentResource::collection($incidents);
    }
}
