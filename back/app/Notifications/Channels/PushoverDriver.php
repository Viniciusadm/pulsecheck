<?php

namespace App\Notifications\Channels;

use App\Models\NotificationChannel;
use App\Notifications\AlertMessage;
use App\Notifications\AlertSeverity;
use Illuminate\Support\Str;

class PushoverDriver extends HttpChannelDriver
{
    public const API_URL = 'https://api.pushover.net/1/messages.json';

    public const KEY_PATTERN = '/^[A-Za-z0-9]{30}$/D';

    public function rules(): array
    {
        return [
            'app_token' => ['required', 'string', 'regex:'.self::KEY_PATTERN],
            'user_key' => ['required', 'string', 'regex:'.self::KEY_PATTERN],
            'device' => ['sometimes', 'nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_,-]+$/D'],
        ];
    }

    public function secretKeys(): array
    {
        return ['app_token', 'user_key'];
    }

    public function send(NotificationChannel $channel, AlertMessage $message): void
    {
        $body = trim(Str::after($message->toText(), $message->title));
        $payload = [
            'token' => $channel->setting('app_token'),
            'user' => $channel->setting('user_key'),
            'title' => Str::limit($message->title, 250),
            'message' => Str::limit($body === '' ? $message->title : $body, 1024),
            'priority' => $message->severity === AlertSeverity::Critical ? 1 : 0,
            'timestamp' => $message->occurredAt->getTimestamp(),
        ];

        if ($channel->setting('device')) {
            $payload['device'] = $channel->setting('device');
        }

        $this->post(self::API_URL, $payload);
    }
}
