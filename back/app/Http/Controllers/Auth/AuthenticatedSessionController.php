<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    public const PENDING_TWO_FACTOR_KEY = 'login.two_factor';

    public const PENDING_TWO_FACTOR_MINUTES = 5;

    public function store(LoginRequest $request, AuditLog $auditLog): UserResource|JsonResponse
    {
        $guard = Auth::guard('web');

        if (! $guard->validate($request->validated())) {
            $auditLog->record('auth.login_failed', null, ['email' => $request->validated('email')]);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user = $guard->getLastAttempted();

        if (! $user instanceof User) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put(self::PENDING_TWO_FACTOR_KEY, [
                'user_id' => $user->id,
                'expires_at' => now()->addMinutes(self::PENDING_TWO_FACTOR_MINUTES)->getTimestamp(),
            ]);

            return response()->json(['two_factor' => true]);
        }

        $guard->login($user);
        $request->session()->regenerate();
        $auditLog->record('auth.login', $user);

        return new UserResource($user);
    }

    public function destroy(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
