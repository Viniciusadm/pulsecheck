<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncidentNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'user' => $this->user === null ? null : ['id' => $this->user->id, 'name' => $this->user->name],
            'can_delete' => $request->user() !== null && ($this->user_id === $request->user()->id || $request->user()->isAdmin()),
            'created_at' => $this->created_at,
        ];
    }
}
