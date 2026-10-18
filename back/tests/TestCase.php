<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    protected function fromSpa(): static
    {
        return $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
            'Accept' => 'application/json',
        ]);
    }

    protected function sessionIdFrom(TestResponse $response): string
    {
        $cookie = $response->getCookie(config('session.cookie'));

        $this->assertNotNull($cookie, 'The response did not set a session cookie.');

        return $cookie->getValue();
    }

    protected function useSession(string $sessionId): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withCookie(config('session.cookie'), $sessionId);
    }

    protected function withoutSession(): static
    {
        $this->app['auth']->forgetGuards();
        unset($this->defaultCookies[config('session.cookie')]);

        return $this;
    }
}
