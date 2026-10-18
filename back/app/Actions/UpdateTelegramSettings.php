<?php

namespace App\Actions;

use App\Models\NotificationSetting;
use App\Models\User;

class UpdateTelegramSettings
{
    public function handle(User $user, array $attributes): NotificationSetting
    {
        $setting = $user->notificationSetting()->firstOrNew();

        $setting->fill(array_filter(
            $attributes,
            fn (mixed $value, string $key) => $key === 'telegram_enabled' || $value !== null,
            ARRAY_FILTER_USE_BOTH,
        ));
        $setting->save();

        return $setting;
    }

    public function remove(User $user): NotificationSetting
    {
        $setting = $user->notificationSetting()->firstOrNew();

        $setting->fill([
            'telegram_bot_token' => null,
            'telegram_chat_id' => null,
            'telegram_enabled' => false,
        ]);
        $setting->save();

        return $setting;
    }
}
