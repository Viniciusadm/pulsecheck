<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckHistoryRequest;
use App\Http\Resources\CheckResultResource;
use App\Models\Monitor;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\Cursor;

class MonitorCheckResultController extends Controller
{
    public function index(CheckHistoryRequest $request, Monitor $monitor): AnonymousResourceCollection
    {
        $cursor = Cursor::fromEncoded($request->query('cursor'));
        $cursorStartedAt = $cursor?->parameter('started_at');

        $checkResults = $monitor->checkResults()
            ->where('started_at', '>=', $request->from())
            ->where('started_at', '<', $request->to())
            ->when($cursorStartedAt !== null, fn ($query) => $query->where('started_at', $cursor->pointsToNextItems() ? '<=' : '>=', $cursorStartedAt))
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->cursorPaginate(50)
            ->withQueryString();

        return CheckResultResource::collection($checkResults);
    }
}
