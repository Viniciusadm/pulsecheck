<?php

namespace App\Http\Controllers;

use App\Http\Requests\StatsPeriodRequest;
use App\Services\DashboardMetrics;
use Illuminate\Http\JsonResponse;

class DashboardErrorBreakdownController extends Controller
{
    public function __invoke(StatsPeriodRequest $request, DashboardMetrics $metrics): JsonResponse
    {
        return response()->json(['data' => $metrics->errorBreakdown($request->user(), $request->period())]);
    }
}
