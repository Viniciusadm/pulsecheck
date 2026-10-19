<?php

namespace App\Http\Controllers;

use App\Exceptions\NotificationDeliveryException;
use App\Models\NotificationChannel;
use App\Notifications\AlertMessage;
use Illuminate\Http\JsonResponse;

class NotificationChannelTestController extends Controller
{
    public function __invoke(NotificationChannel $notificationChannel): JsonResponse
    {
        try {
            $notificationChannel->type->driver()->send($notificationChannel, AlertMessage::test()->formattedAs($notificationChannel->message_format));
        } catch (NotificationDeliveryException $exception) {
            return response()->json(['message' => __($exception->getMessage())], $exception->isRetryable ? 503 : 422);
        }

        return response()->json(['message' => __('Test message sent.')]);
    }
}
