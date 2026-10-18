<?php

namespace App\Http\Controllers;

use App\Http\Requests\StatsPeriodRequest;
use App\Models\Monitor;
use App\Services\LatencySeries;
use Illuminate\Http\JsonResponse;

class MonitorLatencyController extends Controller
{
    public function __invoke(StatsPeriodRequest $request, Monitor $monitor, LatencySeries $latencySeries): JsonResponse
    {
        $period = $request->period();

        return response()->json([
            'data' => [
                'period' => $period->value,
                'bucket_minutes' => $period->bucketMinutes(),
                'buckets' => $latencySeries->buckets($monitor, $period),
            ],
        ]);
    }
}
