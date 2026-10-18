<?php

namespace App\Http\Controllers;

use App\Actions\CreateMonitor;
use App\Actions\UpdateMonitor;
use App\Http\Requests\ListMonitorsRequest;
use App\Http\Requests\StoreMonitorRequest;
use App\Http\Requests\UpdateMonitorRequest;
use App\Http\Resources\MonitorResource;
use App\Models\Monitor;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MonitorController extends Controller
{
    public function index(ListMonitorsRequest $request): AnonymousResourceCollection
    {
        $status = $request->validated('status');

        $monitors = $request->user()->monitors()
            ->with('notificationChannels:id')
            ->when($status === 'paused', fn ($query) => $query->where('is_paused', true))
            ->when($status !== null && $status !== 'paused', fn ($query) => $query->where('is_paused', false)->where('status', $status))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return MonitorResource::collection($monitors);
    }

    public function store(StoreMonitorRequest $request, CreateMonitor $createMonitor): MonitorResource
    {
        return new MonitorResource($createMonitor->handle($request->user(), $request->validated()));
    }

    public function show(Monitor $monitor): MonitorResource
    {
        return new MonitorResource($monitor);
    }

    public function update(UpdateMonitorRequest $request, Monitor $monitor, UpdateMonitor $updateMonitor): MonitorResource
    {
        return new MonitorResource($updateMonitor->handle($monitor, $request->validated()));
    }

    public function destroy(Monitor $monitor): Response
    {
        $monitor->delete();

        return response()->noContent();
    }
}
