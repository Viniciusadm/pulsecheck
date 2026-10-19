<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'users_count' => $this->whenCounted('users'),
            'monitors_count' => $this->whenCounted('monitors'),
            'user_ids' => $this->whenLoaded('users', fn () => $this->users->modelKeys()),
            'monitor_ids' => $this->whenLoaded('monitors', fn () => $this->monitors->modelKeys()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
