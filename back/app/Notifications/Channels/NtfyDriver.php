<?php

namespace App\Notifications\Channels;

use App\Models\NotificationChannel;
use App\Notifications\AlertMessage;
use App\Notifications\AlertSeverity;
use App\Rules\SafeUrl;
use Illuminate\Support\Str;

class NtfyDriver extends HttpChannelDriver
{
    public const DEFAULT_SERVER = 'https://ntfy.sh';

    public const TOPIC_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/D';

    public function rules(): array
    {
        return [
            'topic' => ['required', 'string', 'regex:'.self::TOPIC_PATTERN],
            'server_url' => ['sometimes', 'nullable', 'string', new SafeUrl],
            'access_token' => ['sometimes', 'nullable', 'string', 'max:200', 'regex:/^[A-Za-z0-9_.-]+$/D'],
        ];
    }

    public function secretKeys(): array
    {
        return ['topic', 'access_token'];
    }

    public function send(NotificationChannel $channel, AlertMessage $message): void
    {
        $server = rtrim((string) ($channel->setting('server_url') ?: self::DEFAULT_SERVER), '/');
        $token = $channel->setting('access_token');
        $body = trim(Str::after($message->toText(), $message->title));

        $this->post($server, [
            'topic' => $channel->setting('topic'),
            'title' => Str::limit($message->title, 250),
            'message' => Str::limit($body === '' ? $message->title : $body, 4000),
            'priority' => $this->priority($message->severity),
        ], $token ? ['Authorization' => 'Bearer '.$token] : []);
    }

    private function priority(AlertSeverity $severity): int
    {
        return match ($severity) {
            AlertSeverity::Critical => 5,
            AlertSeverity::Warning => 4,
            AlertSeverity::Resolved, AlertSeverity::Info => 3,
        };
    }
}
