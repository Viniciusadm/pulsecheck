<?php

namespace App\Http\Controllers;

use App\Actions\SaveMaintenanceWindow;
use App\Http\Requests\StoreMaintenanceWindowRequest;
use App\Http\Requests\UpdateMaintenanceWindowRequest;
use App\Http\Resources\MaintenanceWindowResource;
use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MaintenanceWindowController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'monitor_id' => ['sometimes', 'integer'],
        ]);

        $monitor = isset($validated['monitor_id']) ? Monitor::query()->visibleTo($request->user())->find($validated['monitor_id']) : null;

        $windows = MaintenanceWindow::query()
            ->visibleTo($request->user())
            ->when(isset($validated['monitor_id']), fn ($query) => $monitor === null ? $query->whereRaw('false') : $query->affecting($monitor))
            ->with(['monitors:id,name', 'teams:id'])
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return MaintenanceWindowResource::collection($windows);
    }

    public function store(StoreMaintenanceWindowRequest $request, SaveMaintenanceWindow $saveMaintenanceWindow): MaintenanceWindowResource
    {
        $window = $saveMaintenanceWindow->save($request->user(), new MaintenanceWindow, $request->validated());

        return new MaintenanceWindowResource($window->load(['monitors:id,name', 'teams:id']));
    }

    public function show(MaintenanceWindow $maintenanceWindow): MaintenanceWindowResource
    {
        return new MaintenanceWindowResource($maintenanceWindow->load(['monitors:id,name', 'teams:id']));
    }

    public function update(UpdateMaintenanceWindowRequest $request, MaintenanceWindow $maintenanceWindow, SaveMaintenanceWindow $saveMaintenanceWindow): MaintenanceWindowResource
    {
        $window = $saveMaintenanceWindow->save($request->user(), $maintenanceWindow, $request->validated());

        return new MaintenanceWindowResource($window->load(['monitors:id,name', 'teams:id']));
    }

    public function destroy(MaintenanceWindow $maintenanceWindow, SaveMaintenanceWindow $saveMaintenanceWindow): Response
    {
        $saveMaintenanceWindow->delete($maintenanceWindow);

        return response()->noContent();
    }
}
