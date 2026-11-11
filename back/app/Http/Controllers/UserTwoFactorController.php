<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLog;
use App\Services\Auth\TwoFactorAuthentication;
use Illuminate\Http\Response;

class UserTwoFactorController extends Controller
{
    public function destroy(User $user, TwoFactorAuthentication $twoFactor, AuditLog $auditLog): Response
    {
        if ($user->two_factor_secret !== null) {
            $twoFactor->disable($user);
            $auditLog->record('user.two_factor_reset', $user);
        }

        return response()->noContent();
    }
}
