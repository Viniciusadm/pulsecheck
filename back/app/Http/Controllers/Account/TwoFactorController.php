<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ConfirmPasswordRequest;
use App\Models\InstanceSettings;
use App\Services\AuditLog;
use App\Services\Auth\TwoFactorAuthentication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorAuthentication $twoFactor) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => [
            'enabled' => $user->hasTwoFactorEnabled(),
            'pending_confirmation' => $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null,
            'confirmed_at' => $user->two_factor_confirmed_at,
            'recovery_codes_remaining' => $this->twoFactor->remainingRecoveryCodes($user),
        ]]);
    }

    public function store(ConfirmPasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages(['current_password' => __('Two-factor authentication is already enabled.')]);
        }

        $this->twoFactor->begin($user);
        $issuer = InstanceSettings::current()?->name ?: config('app.name');

        return response()->json(['data' => $this->twoFactor->provisioning($user, $issuer)]);
    }

    public function confirm(Request $request, AuditLog $auditLog): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:10']]);
        $codes = $this->twoFactor->confirm($request->user(), $validated['code']);

        if ($codes === null) {
            throw ValidationException::withMessages(['code' => __('The code is invalid or was already used.')]);
        }

        $auditLog->record('account.two_factor_enabled', $request->user());

        return response()->json(['data' => ['recovery_codes' => $codes]]);
    }

    public function destroy(ConfirmPasswordRequest $request, AuditLog $auditLog): Response
    {
        $wasEnabled = $request->user()->hasTwoFactorEnabled();
        $this->twoFactor->disable($request->user());

        if ($wasEnabled) {
            $auditLog->record('account.two_factor_disabled', $request->user());
        }

        return response()->noContent();
    }

    public function recoveryCodes(ConfirmPasswordRequest $request, AuditLog $auditLog): JsonResponse
    {
        if (! $request->user()->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages(['current_password' => __('Two-factor authentication is not enabled.')]);
        }

        $codes = $this->twoFactor->regenerateRecoveryCodes($request->user());
        $auditLog->record('account.recovery_codes_regenerated', $request->user());

        return response()->json(['data' => ['recovery_codes' => $codes]]);
    }
}
