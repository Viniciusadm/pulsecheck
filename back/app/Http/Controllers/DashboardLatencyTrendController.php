<?php

namespace App\Http\Controllers;

use App\Http\Requests\StatsPeriodRequest;
use App\Services\DashboardMetrics;
use Illuminate\Http\JsonResponse;

class DashboardLatencyTrendController extends Controller
{
    public function __invoke(StatsPeriodRequest $request, DashboardMetrics $metrics): JsonResponse
    {
        return response()->json(['data' => $metrics->latencyTrend($request->user(), $request->period())]);
    }
}
