<?php

namespace App\Http\Middleware;

use App\Models\StatusPage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictStatusPageDomains
{
    private const ALLOWED_PATHS = ['up', 'api/config', 'api/user'];

    private const BADGE_PATTERN = '#^api/public/badges/[A-Za-z0-9]{40}/(status|uptime)\.svg$#D';

    public function handle(Request $request, Closure $next): Response
    {
        $statusPage = StatusPage::forHost($request->getHost());

        if ($statusPage === null) {
            return $next($request);
        }

        $path = $request->path();
        $isAllowed = in_array($path, self::ALLOWED_PATHS, true)
            || $path === "api/public/status-pages/{$statusPage->slug}"
            || preg_match(self::BADGE_PATTERN, $path) === 1;

        abort_unless($isAllowed && $request->isMethodSafe(), 404);

        $request->attributes->set(StatusPage::class, $statusPage);

        return $next($request);
    }
}
