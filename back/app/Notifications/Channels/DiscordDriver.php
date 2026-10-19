<?php

namespace App\Notifications\Channels;

use App\Models\NotificationChannel;
use App\Notifications\AlertMessage;
use App\Notifications\AlertSeverity;
use Illuminate\Support\Str;

class DiscordDriver extends HttpChannelDriver
{
    public const WEBHOOK_URL_PATTERN = '#^https://((ptb|canary)\.)?(discord|discordapp)\.com/api/webhooks/\d+/[A-Za-z0-9_-]+$#D';

    public function rules(): array
    {
        return [
            'webhook_url' => ['required', 'string', 'max:500', 'regex:'.self::WEBHOOK_URL_PATTERN],
            'mention_role_id' => ['sometimes', 'nullable', 'string', 'regex:/^\d{17,20}$/D'],
        ];
    }

    public function secretKeys(): array
    {
        return ['webhook_url'];
    }

    public function send(NotificationChannel $channel, AlertMessage $message): void
    {
        $this->post($channel->setting('webhook_url'), $this->payload($message, $message->severity === AlertSeverity::Critical ? $channel->setting('mention_role_id') : null));
    }

    private function payload(AlertMessage $message, ?string $mentionRoleId): array
    {
        $embed = [
            'title' => Str::limit($message->title, 253),
            'color' => $this->color($message->severity),
            'timestamp' => $message->occurredAt->copy()->utc()->toIso8601ZuluString(),
            'fields' => array_map(
                fn (string $label, string $value) => ['name' => $label, 'value' => Str::limit($value === '' ? '-' : $value, 1021), 'inline' => false],
                array_keys($message->fields),
                $message->fields,
            ),
        ];

        if ($message->summary !== null) {
            $embed['description'] = $message->summary;
        }

        if ($mentionRoleId === null) {
            return ['embeds' => [$embed], 'allowed_mentions' => ['parse' => []]];
        }

        return ['content' => "<@&{$mentionRoleId}>", 'embeds' => [$embed], 'allowed_mentions' => ['parse' => [], 'roles' => [$mentionRoleId]]];
    }

    private function color(AlertSeverity $severity): int
    {
        return match ($severity) {
            AlertSeverity::Critical => 0xDC2626,
            AlertSeverity::Warning => 0xD97706,
            AlertSeverity::Resolved => 0x16A34A,
            AlertSeverity::Info => 0x2563EB,
        };
    }
}
