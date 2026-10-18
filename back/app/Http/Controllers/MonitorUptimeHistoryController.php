<?php

namespace App\Http\Controllers;

use App\Http\Requests\UptimeHistoryRequest;
use App\Models\Monitor;
use App\Services\UptimeCalculator;
use Illuminate\Http\JsonResponse;

class MonitorUptimeHistoryController extends Controller
{
    public function __invoke(UptimeHistoryRequest $request, Monitor $monitor, UptimeCalculator $uptimeCalculator): JsonResponse
    {
        return response()->json([
            'data' => $uptimeCalculator->daily($monitor, $request->days()),
        ]);
    }
}
