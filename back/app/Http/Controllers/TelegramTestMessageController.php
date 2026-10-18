<?php

namespace App\Http\Controllers;

use App\Exceptions\TelegramDeliveryException;
use App\Services\TelegramClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TelegramTestMessageController extends Controller
{
    public function __invoke(Request $request, TelegramClient $telegramClient): JsonResponse
    {
        $setting = $request->user()->notificationSetting;

        if ($setting?->telegram_bot_token === null || $setting->telegram_chat_id === null) {
            throw ValidationException::withMessages([
                'telegram' => __('Save the bot token and chat ID before sending a test message.'),
            ]);
        }

        try {
            $telegramClient->sendMessage(
                $setting->telegram_bot_token,
                $setting->telegram_chat_id,
                "✅ PulseCheck test message.\nDown and recovery alerts for your monitors will arrive in this chat.",
            );
        } catch (TelegramDeliveryException $exception) {
            return response()->json(['message' => __($exception->getMessage())], $exception->isRetryable ? 503 : 422);
        }

        return response()->json(['message' => __('Test message sent.')]);
    }
}
