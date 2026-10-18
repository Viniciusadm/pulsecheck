<?php

namespace App\Notifications\Channels;

use App\Models\NotificationChannel;
use App\Notifications\AlertMessage;
use Illuminate\Support\Str;

class SlackDriver extends HttpChannelDriver
{
    public const WEBHOOK_URL_PATTERN = '#^https://hooks\.slack\.com/(services|workflows|triggers)/[A-Za-z0-9/_-]+$#D';

    public function rules(): array
    {
        return [
            'webhook_url' => ['required', 'string', 'max:500', 'regex:'.self::WEBHOOK_URL_PATTERN],
        ];
    }

    public function secretKeys(): array
    {
        return ['webhook_url'];
    }

    public function send(NotificationChannel $channel, AlertMessage $message): void
    {
        $this->post($channel->setting('webhook_url'), $this->payload($message));
    }

    private function payload(AlertMessage $message): array
    {
        $blocks = [
            ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => Str::limit($message->title, 147), 'emoji' => true]],
        ];

        if ($message->summary !== null) {
            $blocks[] = ['type' => 'section', 'text' => ['type' => 'plain_text', 'text' => $message->summary, 'emoji' => true]];
        }

        if ($message->fields !== []) {
            $blocks[] = [
                'type' => 'section',
                'fields' => array_map(
                    fn (string $label, string $value) => ['type' => 'mrkdwn', 'text' => Str::limit('*'.$this->escape($label)."*\n".$this->escape($value), 1997)],
                    array_keys($message->fields),
                    $message->fields,
                ),
            ];
        }

        return ['text' => $this->escape($message->toText()), 'blocks' => $blocks];
    }

    private function escape(string $text): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
    }
}
