<?php

namespace App\Actions;

use App\Models\NotificationChannel;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveNotificationChannel
{
    public function __construct(private SyncScopedRelation $syncScopedRelation) {}

    public function create(array $attributes): NotificationChannel
    {
        return DB::transaction(function () use ($attributes): NotificationChannel {
            $channel = NotificationChannel::create(Arr::except($attributes, 'team_ids'));
            $channel->teams()->sync($attributes['team_ids'] ?? []);

            return $channel;
        });
    }

    public function update(User $user, NotificationChannel $channel, array $attributes): NotificationChannel
    {
        if (array_key_exists('config', $attributes)) {
            $attributes['config'] = $this->mergeConfig($channel, $attributes['config']);
        }

        return DB::transaction(function () use ($user, $channel, $attributes): NotificationChannel {
            $channel->update(Arr::except($attributes, 'team_ids'));

            if (array_key_exists('team_ids', $attributes)) {
                $this->syncScopedRelation->handle($channel->teams(), $attributes['team_ids'], $user->visibleTeamIds());
            }

            return $channel;
        });
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
