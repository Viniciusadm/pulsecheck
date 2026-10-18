<?php

namespace App\Http\Controllers;

use App\Models\Monitor;
use Illuminate\Http\JsonResponse;

class DashboardSummaryController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $counts = Monitor::query()
            ->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw("count(*) filter (where not is_paused and status = 'up') as up")
            ->selectRaw("count(*) filter (where not is_paused and status = 'down') as down")
            ->selectRaw("count(*) filter (where not is_paused and status = 'pending') as pending")
            ->selectRaw('count(*) filter (where is_paused) as paused')
            ->first();

        return response()->json([
            'data' => array_map('intval', (array) $counts),
        ]);
    }
}
