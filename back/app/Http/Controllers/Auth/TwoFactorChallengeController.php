<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLog;
use App\Services\Auth\TwoFactorAuthentication;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TwoFactorChallengeController extends Controller
{
    public function __invoke(TwoFactorChallengeRequest $request, TwoFactorAuthentication $twoFactor, AuditLog $auditLog): UserResource
    {
        $pending = $request->session()->get(AuthenticatedSessionController::PENDING_TWO_FACTOR_KEY);
        $user = is_array($pending) && ($pending['expires_at'] ?? 0) >= now()->getTimestamp()
            ? User::query()->find($pending['user_id'] ?? null)
            : null;

        if ($user === null || ! $user->hasTwoFactorEnabled()) {
            $request->session()->forget(AuthenticatedSessionController::PENDING_TWO_FACTOR_KEY);

            throw ValidationException::withMessages(['code' => __('Your login attempt expired. Log in again.')]);
        }

        $recoveryCode = $request->validated('recovery_code');
        $isValid = filled($recoveryCode)
            ? $twoFactor->consumeRecoveryCode($user, $recoveryCode)
            : $twoFactor->verifyCode($user, (string) $request->validated('code'));

        if (! $isValid) {
            $auditLog->record('auth.two_factor_failed', $user, actor: $user);

            throw ValidationException::withMessages([
                filled($recoveryCode) ? 'recovery_code' : 'code' => __('The code is invalid or was already used.'),
            ]);
        }

        $request->session()->forget(AuthenticatedSessionController::PENDING_TWO_FACTOR_KEY);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $auditLog->record(filled($recoveryCode) ? 'auth.login_recovery_code' : 'auth.login', $user, actor: $user);

        return new UserResource($user);
    }
}
