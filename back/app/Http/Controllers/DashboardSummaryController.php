<?php

namespace App\Http\Controllers;

use App\Models\Monitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardSummaryController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $counts = Monitor::query()
            ->visibleTo($request->user())
            ->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw("count(*) filter (where not is_paused and maintenance_ends_at is null and status = 'up') as up")
            ->selectRaw("count(*) filter (where not is_paused and maintenance_ends_at is null and status = 'down') as down")
            ->selectRaw("count(*) filter (where not is_paused and maintenance_ends_at is null and status = 'degraded') as degraded")
            ->selectRaw("count(*) filter (where not is_paused and maintenance_ends_at is null and status = 'pending') as pending")
            ->selectRaw('count(*) filter (where not is_paused and maintenance_ends_at is not null) as maintenance')
            ->selectRaw('count(*) filter (where is_paused) as paused')
            ->first();

        return response()->json([
            'data' => array_map('intval', (array) $counts),
        ]);
    }
}
