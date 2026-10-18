<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateUser
{
    public function __construct(private SyncUserAccess $syncUserAccess) {}

    public function handle(array $attributes): User
    {
        return DB::transaction(function () use ($attributes): User {
            $user = new User(Arr::except($attributes, 'team_ids'));
            $this->syncUserAccess->handle($user, $attributes['team_ids'] ?? []);

            return $user;
        });
    }
}
