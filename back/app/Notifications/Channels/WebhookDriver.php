<?php

namespace App\Notifications\Channels;

use App\Models\NotificationChannel;
use App\Notifications\AlertMessage;
use App\Rules\HttpHeaderNames;
use App\Rules\SafeUrl;

class WebhookDriver extends HttpChannelDriver
{
    public const MAX_HEADERS = 10;

    private const RESERVED_HEADERS = ['host', 'content-length', 'content-type', 'transfer-encoding', 'connection', 'user-agent'];

    public function rules(): array
    {
        return [
            'url' => ['required', 'string', new SafeUrl],
            'headers' => ['sometimes', 'array', 'max:'.self::MAX_HEADERS, new HttpHeaderNames(self::RESERVED_HEADERS)],
            'headers.*' => ['required', 'string', 'max:1000', 'regex:/^[^\r\n\x00]*$/D'],
        ];
    }

    public function secretKeys(): array
    {
        return ['url', 'headers'];
    }

    public function send(NotificationChannel $channel, AlertMessage $message): void
    {
        $this->post($channel->setting('url'), $message->toPayload(), $channel->setting('headers') ?? []);
    }
}
