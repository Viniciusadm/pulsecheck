<?php

namespace App\Notifications\Channels;

use App\Models\NotificationChannel;
use App\Notifications\AlertMessage;
use App\Services\TelegramClient;

class TelegramDriver implements ChannelDriver
{
    public const BOT_TOKEN_PATTERN = '/^\d+:[A-Za-z0-9_-]{30,}$/D';

    public const CHAT_ID_PATTERN = '/^(-?\d{1,20}|@[A-Za-z][A-Za-z0-9_]{4,31})$/D';

    public function __construct(private TelegramClient $client) {}

    public function rules(): array
    {
        return [
            'bot_token' => ['required', 'string', 'regex:'.self::BOT_TOKEN_PATTERN],
            'chat_id' => ['required', 'string', 'regex:'.self::CHAT_ID_PATTERN],
            'silent' => ['sometimes', 'boolean'],
        ];
    }

    public function secretKeys(): array
    {
        return ['bot_token'];
    }

    public function send(NotificationChannel $channel, AlertMessage $message): void
    {
        $this->client->sendMessage($channel->setting('bot_token'), $channel->setting('chat_id'), $message->toText(), (bool) $channel->setting('silent'));
    }
}
