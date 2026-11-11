<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Security\Totp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TwoFactorAuthentication
{
    public const RECOVERY_CODE_COUNT = 8;

    public function __construct(private readonly Totp $totp) {}

    public function begin(User $user): string
    {
        $secret = $this->totp->generateSecret();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_step' => null,
        ])->save();

        return $secret;
    }

    public function provisioning(User $user, string $issuer): array
    {
        $uri = $this->totp->provisioningUri((string) $user->two_factor_secret, $user->email, $issuer);

        return [
            'secret' => $user->two_factor_secret,
            'otpauth_url' => $uri,
            'qr_code_svg' => $this->totp->qrCodeSvg($uri),
        ];
    }

    public function confirm(User $user, string $code): ?array
    {
        if ($user->two_factor_secret === null || $user->two_factor_confirmed_at !== null) {
            return null;
        }

        $step = $this->totp->matchingStep($user->two_factor_secret, $code);

        if ($step === null) {
            return null;
        }

        $codes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_last_used_step' => $step,
            'two_factor_recovery_codes' => array_map($this->hashRecoveryCode(...), $codes),
        ])->save();

        return $codes;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_step' => null,
        ])->save();
    }

    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = $this->generateRecoveryCodes();

        $user->forceFill(['two_factor_recovery_codes' => array_map($this->hashRecoveryCode(...), $codes)])->save();

        return $codes;
    }

    public function remainingRecoveryCodes(User $user): int
    {
        return count($user->two_factor_recovery_codes ?? []);
    }

    public function verifyCode(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code): bool {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if (! $locked->hasTwoFactorEnabled()) {
                return false;
            }

            $step = $this->totp->matchingStep($locked->two_factor_secret, $code, $locked->two_factor_last_used_step);

            if ($step === null) {
                return false;
            }

            $locked->forceFill(['two_factor_last_used_step' => $step])->save();
            $user->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }

    public function consumeRecoveryCode(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code): bool {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if (! $locked->hasTwoFactorEnabled()) {
                return false;
            }

            $hash = $this->hashRecoveryCode($code);
            $codes = $locked->two_factor_recovery_codes ?? [];
            $remaining = array_values(array_filter($codes, fn (string $stored): bool => ! hash_equals($stored, $hash)));

            if (count($remaining) === count($codes)) {
                return false;
            }

            $locked->forceFill(['two_factor_recovery_codes' => $remaining])->save();
            $user->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }

    private function generateRecoveryCodes(): array
    {
        return array_map(
            fn (): string => Str::lower(Str::random(5).'-'.Str::random(5)),
            range(1, self::RECOVERY_CODE_COUNT),
        );
    }

    private function hashRecoveryCode(string $code): string
    {
        return hash_hmac('sha256', Str::lower(trim($code)), (string) config('app.key'));
    }
}
