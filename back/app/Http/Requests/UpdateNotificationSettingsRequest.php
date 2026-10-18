<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateNotificationSettingsRequest extends FormRequest
{
    public const BOT_TOKEN_PATTERN = '/^\d+:[A-Za-z0-9_-]{30,}$/D';

    public const CHAT_ID_PATTERN = '/^(-?\d{1,20}|@[A-Za-z][A-Za-z0-9_]{4,31})$/D';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'telegram_bot_token' => ['nullable', 'string', 'regex:'.self::BOT_TOKEN_PATTERN],
            'telegram_chat_id' => ['nullable', 'string', 'regex:'.self::CHAT_ID_PATTERN],
            'telegram_enabled' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'telegram_bot_token.regex' => __('The bot token must look like 123456789:ABC... as given by @BotFather.'),
            'telegram_chat_id.regex' => __('The chat ID must be a number (it may start with -) or a @channel name.'),
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || ! $this->boolean('telegram_enabled')) {
                    return;
                }

                $setting = $this->user()->notificationSetting;
                $hasToken = $this->filled('telegram_bot_token') || $setting?->telegram_bot_token !== null;
                $hasChatId = $this->filled('telegram_chat_id') || $setting?->telegram_chat_id !== null;

                if (! $hasToken || ! $hasChatId) {
                    $validator->errors()->add('telegram_enabled', __('Add the bot token and chat ID before enabling Telegram notifications.'));
                }
            },
        ];
    }
}
