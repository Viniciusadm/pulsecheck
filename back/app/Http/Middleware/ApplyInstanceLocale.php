<?php

namespace App\Http\Middleware;

use App\Models\InstanceSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class ApplyInstanceLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale(InstanceSettings::currentLocale());

        return $next($request);
    }
}
