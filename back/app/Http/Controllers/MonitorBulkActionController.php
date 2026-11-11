<?php

namespace App\Http\Controllers;

use App\Actions\PauseMonitor;
use App\Actions\ResumeMonitor;
use App\Http\Requests\BulkMonitorActionRequest;
use App\Models\Monitor;
use Illuminate\Http\JsonResponse;

class MonitorBulkActionController extends Controller
{
    public function __invoke(BulkMonitorActionRequest $request, PauseMonitor $pauseMonitor, ResumeMonitor $resumeMonitor): JsonResponse
    {
        $user = $request->user();
        $monitors = Monitor::query()
            ->visibleTo($user)
            ->whereIn('id', $request->validated('ids'))
            ->get()
            ->filter(fn (Monitor $monitor) => $user->can('update', $monitor));

        foreach ($monitors as $monitor) {
            match ($request->validated('action')) {
                'pause' => $pauseMonitor->handle($monitor),
                'resume' => $resumeMonitor->handle($monitor),
                'delete' => $monitor->delete(),
            };
        }

        return response()->json(['data' => ['affected' => $monitors->count()]]);
    }
}
