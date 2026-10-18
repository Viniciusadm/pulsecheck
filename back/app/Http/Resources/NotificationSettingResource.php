<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'telegram_enabled' => (bool) $this->telegram_enabled,
            'telegram_chat_id' => $this->telegram_chat_id,
            'has_telegram_token' => $this->telegram_bot_token !== null,
        ];
    }
}
