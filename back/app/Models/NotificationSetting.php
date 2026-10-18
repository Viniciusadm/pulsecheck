<?php

namespace App\Models;

use Database\Factories\NotificationSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['telegram_bot_token', 'telegram_chat_id', 'telegram_enabled'])]
#[Hidden(['telegram_bot_token'])]
class NotificationSetting extends Model
{
    /** @use HasFactory<NotificationSettingFactory> */
    use HasFactory;

    protected $attributes = [
        'telegram_enabled' => false,
    ];

    protected function casts(): array
    {
        return [
            'telegram_bot_token' => 'encrypted',
            'telegram_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isTelegramReady(): bool
    {
        return $this->telegram_enabled
            && $this->telegram_bot_token !== null
            && $this->telegram_chat_id !== null;
    }
}
