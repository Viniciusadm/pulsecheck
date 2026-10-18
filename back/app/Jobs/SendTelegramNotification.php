<?php

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Exceptions\TelegramDeliveryException;
use App\Models\Incident;
use App\Services\TelegramClient;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendTelegramNotification implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct(
        public int $incidentId,
        public NotificationType $type,
    ) {}

    public function uniqueId(): string
    {
        return "{$this->incidentId}:{$this->type->value}";
    }

    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(TelegramClient $telegramClient): void
    {
        $incident = Incident::query()->with('monitor.user.notificationSetting')->find($this->incidentId);
        $setting = $incident?->monitor->user->notificationSetting;

        if ($incident === null || ! $setting?->isTelegramReady()) {
            return;
        }

        if ($this->type === NotificationType::Down && $incident->resolved_at !== null) {
            return;
        }

        try {
            $telegramClient->sendMessage($setting->telegram_bot_token, $setting->telegram_chat_id, $this->message($incident));
        } catch (TelegramDeliveryException $exception) {
            $willRetry = $exception->isRetryable && $this->attempts() < $this->tries;

            Log::warning('Telegram notification failed.', [
                'incident_id' => $this->incidentId,
                'monitor_id' => $incident->monitor_id,
                'type' => $this->type->value,
                'reason' => $exception->getMessage(),
                'attempt' => $this->attempts(),
                'will_retry' => $willRetry,
            ]);

            if ($willRetry) {
                $this->release($this->backoff()[$this->attempts() - 1] ?? 120);
            }
        }
    }

    private function message(Incident $incident): string
    {
        $monitor = $incident->monitor;
        $host = $this->host($monitor->url);

        return match ($this->type) {
            NotificationType::Down => implode("\n", [
                "🔴 {$monitor->name} is DOWN",
                "Host: {$host}",
                "Cause: {$incident->cause}",
                'Since: '.$this->formatTime($incident->started_at),
            ]),
            NotificationType::Recovered => implode("\n", [
                "🟢 {$monitor->name} is back UP",
                "Host: {$host}",
                'Downtime: '.$this->formatDuration($incident->duration_seconds),
                'Resolved: '.$this->formatTime($incident->resolved_at ?? now()),
            ]),
        };
    }

    private function host(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?? '';
        $port = parse_url($url, PHP_URL_PORT);

        return $port === null ? $host : "{$host}:{$port}";
    }

    private function formatTime(CarbonInterface $moment): string
    {
        return $moment->copy()->utc()->format('Y-m-d H:i:s').' UTC';
    }

    private function formatDuration(int $seconds): string
    {
        $parts = array_filter([
            'h' => intdiv($seconds, 3600),
            'm' => intdiv($seconds % 3600, 60),
            's' => $seconds % 60,
        ]);

        if ($parts === []) {
            return '0s';
        }

        return implode(' ', array_map(fn (int $value, string $unit) => $value.$unit, $parts, array_keys($parts)));
    }
}
