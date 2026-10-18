<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\InstanceSettings;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CompleteSetup
{
    private const LOCK_KEY = 7_371_000_001;

    public function handle(array $attributes): User
    {
        return DB::transaction(function () use ($attributes): User {
            DB::statement('select pg_advisory_xact_lock(?)', [self::LOCK_KEY]);

            if (InstanceSettings::query()->exists()) {
                throw new ConflictHttpException(__('PulseCheck has already been set up.'));
            }

            InstanceSettings::create([
                'name' => $attributes['instance_name'],
                'timezone' => $attributes['timezone'],
                'locale' => $attributes['locale'] ?? config('pulsecheck.default_locale'),
            ]);

            return User::create([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'password' => $attributes['password'],
                'role' => UserRole::Admin,
            ]);
        });
    }
}
