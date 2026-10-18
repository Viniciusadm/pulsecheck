<?php

namespace App\Http\Controllers;

use App\Http\Requests\StatsPeriodRequest;
use App\Models\Monitor;
use App\Services\UptimeCalculator;
use Illuminate\Http\JsonResponse;

class MonitorStatsController extends Controller
{
    public function __invoke(StatsPeriodRequest $request, Monitor $monitor, UptimeCalculator $uptimeCalculator): JsonResponse
    {
        $period = $request->period();
        $to = now();

        return response()->json([
            'data' => [
                'period' => $period->value,
                'from' => $to->copy()->subHours($period->hours())->toIso8601ZuluString(),
                'to' => $to->toIso8601ZuluString(),
                ...$uptimeCalculator->stats($monitor, $to->copy()->subHours($period->hours()), $to),
            ],
        ]);
    }
}
