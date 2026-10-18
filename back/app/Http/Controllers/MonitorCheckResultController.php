<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckHistoryRequest;
use App\Http\Resources\CheckResultResource;
use App\Models\Monitor;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MonitorCheckResultController extends Controller
{
    public function index(CheckHistoryRequest $request, Monitor $monitor): AnonymousResourceCollection
    {
        $checkResults = $monitor->checkResults()
            ->where('started_at', '>=', $request->from())
            ->where('started_at', '<', $request->to())
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->cursorPaginate(50)
            ->withQueryString();

        return CheckResultResource::collection($checkResults);
    }
}
