<?php

namespace App\Http\Middleware;

use App\Enums\TokenAbility;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnforceTokenAbilities
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken && ! $request->isMethodSafe() && ! $token->can(TokenAbility::Write->value)) {
            abort(403, __('This API token cannot change data.'));
        }

        return $next($request);
    }
}
