<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListAuditLogRequest;
use App\Http\Resources\AuditLogEntryResource;
use App\Models\AuditLogEntry;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController extends Controller
{
    public function __invoke(ListAuditLogRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $entries = AuditLogEntry::query()
            ->when($filters['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($filters['subject_type'] ?? null, fn ($query, $type) => $query->where('subject_type', $type))
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('created_at', '<=', $to))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return AuditLogEntryResource::collection($entries);
    }
}
