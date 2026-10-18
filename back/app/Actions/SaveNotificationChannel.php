<?php

namespace App\Actions;

use App\Models\NotificationChannel;

class SaveNotificationChannel
{
    public function create(array $attributes): NotificationChannel
    {
        return NotificationChannel::create($attributes);
    }

    public function update(NotificationChannel $channel, array $attributes): NotificationChannel
    {
        if (array_key_exists('config', $attributes)) {
            $attributes['config'] = $this->mergeConfig($channel, $attributes['config']);
        }

        $channel->update($attributes);

        return $channel;
    }

    private function mergeConfig(NotificationChannel $channel, array $config): array
    {
        $preserved = collect($channel->type->driver()->secretKeys())
            ->filter(fn (string $key) => ($config[$key] ?? null) === null && array_key_exists($key, $channel->config ?? []))
            ->mapWithKeys(fn (string $key) => [$key => $channel->config[$key]])
            ->all();

        return [...array_filter($config, fn (mixed $value) => $value !== null), ...$preserved];
    }
}
