<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'latency_ms' => $this->latency_ms,
            'status_code' => $this->status_code,
            'is_success' => $this->is_success,
            'error_type' => $this->error_type,
            'error_message' => $this->error_message,
        ];
    }
}
