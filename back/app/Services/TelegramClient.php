<?php

namespace App\Services;

use App\Exceptions\NotificationDeliveryException;
use Illuminate\Support\Facades\Http;
use Throwable;

class TelegramClient
{
    private const API_URL = 'https://api.telegram.org';

    public function sendMessage(string $botToken, string $chatId, string $text, bool $isSilent = false): void
    {
        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->asForm()
                ->post(self::API_URL."/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'disable_web_page_preview' => 'true',
                    'disable_notification' => $isSilent ? 'true' : 'false',
                ]);
        } catch (Throwable) {
            throw NotificationDeliveryException::unavailable();
        }

        if ($response->successful() && $response->json('ok') === true) {
            return;
        }

        throw NotificationDeliveryException::forStatus($response->status());
    }
}
