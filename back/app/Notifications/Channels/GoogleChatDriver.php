<?php

namespace App\Notifications\Channels;

use App\Models\NotificationChannel;
use App\Notifications\AlertMessage;
use Illuminate\Support\Str;

class GoogleChatDriver extends HttpChannelDriver
{
    public const WEBHOOK_URL_PATTERN = '#^https://chat\.googleapis\.com/v1/spaces/[A-Za-z0-9_-]+/messages\?[^\s]+$#D';

    public function rules(): array
    {
        return [
            'webhook_url' => ['required', 'string', 'max:1000', 'regex:'.self::WEBHOOK_URL_PATTERN],
        ];
    }

    public function secretKeys(): array
    {
        return ['webhook_url'];
    }

    public function send(NotificationChannel $channel, AlertMessage $message): void
    {
        $lines = ['*'.$message->title.'*'];

        if ($message->summary !== null) {
            $lines[] = $message->summary;
        }

        foreach ($message->fields as $label => $value) {
            $lines[] = "*{$label}:* {$value}";
        }

        $this->post($channel->setting('webhook_url'), ['text' => Str::limit(implode("\n", $lines), 4000)]);
    }
}
