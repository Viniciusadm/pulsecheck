<?php

namespace App\Console\Commands;

use App\Models\AuditLogEntry;
use App\Models\User;
use App\Services\Auth\TwoFactorAuthentication;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pulsecheck:reset-two-factor {email : Email of the account that lost access}')]
#[Description('Turn off two-factor authentication for an account that lost its device and recovery codes')]
class ResetTwoFactor extends Command
{
    public function handle(TwoFactorAuthentication $twoFactor): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->components->error("No account uses {$email}.");

            return self::FAILURE;
        }

        if ($user->two_factor_secret === null) {
            $this->components->info("Two-factor authentication is already off for {$email}.");

            return self::SUCCESS;
        }

        $twoFactor->disable($user);
        AuditLogEntry::query()->create([
            'action' => 'user.two_factor_reset',
            'actor_name' => 'Console',
            'subject_type' => 'user',
            'subject_id' => $user->id,
            'subject_label' => $user->name,
        ]);

        $this->components->info("Two-factor authentication is off for {$email}. They can log in with their password and turn it on again.");

        return self::SUCCESS;
    }
}
