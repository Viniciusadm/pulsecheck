<?php

namespace App\Http\Middleware;

use App\Models\InstanceSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupIsPending
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(InstanceSettings::current() !== null, Response::HTTP_CONFLICT, __('PulseCheck has already been set up.'));

        return $next($request);
    }
}
