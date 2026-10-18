<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class NotificationChannelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $config = $this->config ?? [];
        $secretKeys = $this->type->driver()->secretKeys();

        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'enabled' => $this->enabled,
            'is_default' => $this->is_default,
            'config' => (object) ($request->user()?->canManageResources() ? Arr::except($config, $secretKeys) : []),
            'secrets_set' => array_values(array_filter($secretKeys, fn (string $key) => ! empty($config[$key]))),
            'monitors_count' => $this->whenCounted('monitors'),
            'team_ids' => $this->teams->modelKeys(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
