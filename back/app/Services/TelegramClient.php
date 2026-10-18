<?php

namespace App\Services;

use App\Exceptions\TelegramDeliveryException;
use Illuminate\Support\Facades\Http;
use Throwable;

class TelegramClient
{
    private const API_URL = 'https://api.telegram.org';

    private const REJECTION_STATUSES = [400, 401, 403, 404];

    public function sendMessage(string $botToken, string $chatId, string $text): void
    {
        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->asForm()
                ->post(self::API_URL."/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'disable_web_page_preview' => 'true',
                ]);
        } catch (Throwable) {
            throw TelegramDeliveryException::unavailable();
        }

        if ($response->successful() && $response->json('ok') === true) {
            return;
        }

        throw in_array($response->status(), self::REJECTION_STATUSES, true)
            ? TelegramDeliveryException::rejected()
            : TelegramDeliveryException::unavailable();
    }
}
