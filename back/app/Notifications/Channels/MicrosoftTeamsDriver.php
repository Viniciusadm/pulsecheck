<?php

namespace App\Notifications\Channels;

use App\Models\NotificationChannel;
use App\Notifications\AlertMessage;
use App\Notifications\AlertSeverity;
use Illuminate\Support\Str;

class MicrosoftTeamsDriver extends HttpChannelDriver
{
    public const WEBHOOK_URL_PATTERN = '#^https://[A-Za-z0-9.-]+\.(logic\.azure\.com|powerplatform\.com|webhook\.office\.com)(:443)?/[^\s]+$#D';

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
        $this->post($channel->setting('webhook_url'), $this->payload($message));
    }

    private function payload(AlertMessage $message): array
    {
        $body = [
            ['type' => 'TextBlock', 'text' => Str::limit($message->title, 250), 'weight' => 'Bolder', 'size' => 'Medium', 'wrap' => true, 'color' => $this->color($message->severity)],
        ];

        if ($message->summary !== null) {
            $body[] = ['type' => 'TextBlock', 'text' => $message->summary, 'wrap' => true];
        }

        if ($message->fields !== []) {
            $body[] = [
                'type' => 'FactSet',
                'facts' => array_map(
                    fn (string $label, string $value) => ['title' => $label, 'value' => $value === '' ? '-' : Str::limit($value, 1000)],
                    array_keys($message->fields),
                    $message->fields,
                ),
            ];
        }

        return [
            'type' => 'message',
            'attachments' => [[
                'contentType' => 'application/vnd.microsoft.card.adaptive',
                'contentUrl' => null,
                'content' => [
                    '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                    'type' => 'AdaptiveCard',
                    'version' => '1.4',
                    'body' => $body,
                ],
            ]],
        ];
    }

    private function color(AlertSeverity $severity): string
    {
        return match ($severity) {
            AlertSeverity::Critical => 'Attention',
            AlertSeverity::Warning => 'Warning',
            AlertSeverity::Resolved => 'Good',
            AlertSeverity::Info => 'Accent',
        };
    }
}
