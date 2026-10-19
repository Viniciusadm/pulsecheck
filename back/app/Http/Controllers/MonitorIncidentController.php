<?php

namespace App\Http\Controllers;

use App\Http\Resources\IncidentResource;
use App\Models\Monitor;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MonitorIncidentController extends Controller
{
    public function index(Monitor $monitor): AnonymousResourceCollection
    {
        $incidents = $monitor->incidents()
            ->with('acknowledger:id,name')
            ->withCount('notes')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate(20);

        return IncidentResource::collection($incidents);
    }
}
