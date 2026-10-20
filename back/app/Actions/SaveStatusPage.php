<?php

namespace App\Actions;

use App\Models\Monitor;
use App\Models\StatusPage;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveStatusPage
{
    public function __construct(private SyncScopedRelation $syncScopedRelation, private SyncStatusPageMonitors $syncStatusPageMonitors) {}

    public function save(User $user, StatusPage $statusPage, array $attributes): StatusPage
    {
        try {
            return $this->persist($user, $statusPage, $attributes);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['slug' => __('This address is already in use.')]);
        }
    }

    private function persist(User $user, StatusPage $statusPage, array $attributes): StatusPage
    {
        return DB::transaction(function () use ($user, $statusPage, $attributes): StatusPage {
            $isNew = ! $statusPage->exists;
            Cache::forget($statusPage->cacheKey());
            $statusPage->fill(Arr::except($attributes, ['monitors', 'team_ids']));
            $statusPage->save();

            if (array_key_exists('team_ids', $attributes)) {
                $isNew
                    ? $statusPage->teams()->sync($attributes['team_ids'])
                    : $this->syncScopedRelation->handle($statusPage->teams(), $attributes['team_ids'], $user->visibleTeamIds());
            }

            if (array_key_exists('monitors', $attributes)) {
                $this->syncStatusPageMonitors->handle($statusPage, $attributes['monitors'], Monitor::query()->visibleTo($user)->pluck('id')->all());
            }

            $statusPage->touch();
            Cache::forget($statusPage->cacheKey());

            return $statusPage;
        });
    }
}
