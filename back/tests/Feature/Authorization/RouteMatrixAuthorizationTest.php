<?php

namespace Tests\Feature\Authorization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

class RouteMatrixAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const PUBLIC_API_ROUTES = [
        'POST api/auth/login',
        'POST api/auth/forgot-password',
        'POST api/auth/reset-password',
        'GET api/config',
        'POST api/setup',
        'GET api/heartbeat/{token}',
        'GET api/public/status-pages/{slug}',
        'GET api/public/badges/{badgeToken}/status.svg',
        'GET api/public/badges/{badgeToken}/uptime.svg',
    ];

    public function test_every_private_api_route_requires_authentication(): void
    {
        $privateRoutes = $this->apiRoutes()->reject(fn (Route $route) => $this->isPublic($route));

        $this->assertNotEmpty($privateRoutes);

        foreach ($privateRoutes as $route) {
            $method = $route->methods()[0];
            $uri = '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri());

            $this->fromSpa()->json($method, $uri)->assertUnauthorized();
        }
    }

    public function test_only_allowlisted_api_routes_skip_authentication(): void
    {
        $unauthenticatedRoutes = $this->apiRoutes()
            ->reject(fn (Route $route) => in_array('auth:sanctum', $route->gatherMiddleware(), true))
            ->map(fn (Route $route) => $this->describe($route))
            ->sort()
            ->values()
            ->all();

        $expected = collect(self::PUBLIC_API_ROUTES)->sort()->values()->all();

        $this->assertSame($expected, $unauthenticatedRoutes);
    }

    private function apiRoutes(): Collection
    {
        return collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => str_starts_with($route->uri(), 'api/'));
    }

    private function isPublic(Route $route): bool
    {
        return in_array($this->describe($route), self::PUBLIC_API_ROUTES, true);
    }

    private function describe(Route $route): string
    {
        return $route->methods()[0].' '.$route->uri();
    }
}
