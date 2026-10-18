<?php

namespace App\Notifications\Channels;

use App\Exceptions\NotificationDeliveryException;
use App\Mail\AlertMail;
use App\Models\NotificationChannel;
use App\Notifications\AlertMessage;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailDriver implements ChannelDriver
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:255', 'email:rfc'],
        ];
    }

    public function secretKeys(): array
    {
        return [];
    }

    public function send(NotificationChannel $channel, AlertMessage $message): void
    {
        try {
            Mail::to($channel->setting('email'))->send(new AlertMail($message));
        } catch (Throwable) {
            throw NotificationDeliveryException::unavailable();
        }
    }
}
