<?php

namespace Tests\Feature\Checks;

use App\Enums\CheckErrorType;
use App\Enums\WarningType;
use App\Models\Monitor;
use App\Services\Checkers\HttpChecker;
use App\Services\CheckOutcome;
use GuzzleHttp\Psr7\Response;
use Tests\Concerns\FakesDns;
use Tests\Concerns\FakesOutboundHttp;
use Tests\TestCase;

class HttpRequestOptionsTest extends TestCase
{
    use FakesDns;
    use FakesOutboundHttp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeDns([
            'example.com' => ['93.184.215.14'],
            'www.example.com' => ['93.184.215.15'],
            'other.example.net' => ['93.184.215.16'],
        ]);
    }

    private function check(array $attributes, Response ...$responses): CheckOutcome
    {
        $this->fakeOutboundHttp(...$responses);

        $monitor = Monitor::factory()->make(['url' => 'https://example.com/health', 'timeout_seconds' => 10, ...$attributes]);

        return $this->app->make(HttpChecker::class)->check($monitor);
    }

    public function test_defaults_send_a_get_without_extra_headers(): void
    {
        $this->check([], new Response(200));

        $this->assertSame('GET', $this->outboundRequests[0]['method']);
        $this->assertArrayNotHasKey('Authorization', $this->outboundRequests[0]['headers']);
    }

    public function test_method_headers_body_and_bearer_token_are_sent(): void
    {
        $outcome = $this->check([
            'options' => ['method' => 'POST', 'body_content_type' => 'application/json'],
            'secrets' => [
                'headers' => ['X-Api-Key' => 'abc123'],
                'body' => '{"ping":true}',
                'auth' => ['type' => 'bearer', 'token' => 'secret-token'],
            ],
        ], new Response(200));

        $request = $this->outboundRequests[0];
        $this->assertTrue($outcome->isSuccess);
        $this->assertSame('POST', $request['method']);
        $this->assertSame('abc123', $request['headers']['X-Api-Key']);
        $this->assertSame('application/json', $request['headers']['Content-Type']);
        $this->assertSame('Bearer secret-token', $request['headers']['Authorization']);
        $this->assertSame(['ping' => true], $request['json']);
    }

    public function test_basic_auth_is_encoded(): void
    {
        $this->check(['secrets' => ['auth' => ['type' => 'basic', 'username' => 'user', 'password' => 'pass']]], new Response(200));

        $this->assertSame('Basic '.base64_encode('user:pass'), $this->outboundRequests[0]['headers']['Authorization']);
    }

    public function test_body_is_not_sent_with_get(): void
    {
        $this->check(['secrets' => ['body' => '{"x":1}']], new Response(200));

        $this->assertNull($this->outboundRequests[0]['json']);
    }

    public function test_expected_status_codes_replace_the_default_range(): void
    {
        $this->assertTrue($this->check(['options' => ['expected_status_codes' => ['401']]], new Response(401))->isSuccess);

        $outcome = $this->check(['options' => ['expected_status_codes' => ['200-204']]], new Response(301, ['Location' => '/x']), new Response(302));
        $this->assertFalse($outcome->isSuccess);
        $this->assertSame(CheckErrorType::HttpStatus, $outcome->errorType);
    }

    public function test_redirects_are_not_followed_when_disabled(): void
    {
        $outcome = $this->check(['options' => ['follow_redirects' => false, 'expected_status_codes' => ['301']]], new Response(301, ['Location' => 'https://www.example.com/']));

        $this->assertTrue($outcome->isSuccess);
        $this->assertSame(301, $outcome->statusCode);
        $this->assertCount(1, $this->outboundRequests);
    }

    public function test_303_switches_a_post_to_get_without_body(): void
    {
        $this->check(
            ['options' => ['method' => 'POST'], 'secrets' => ['body' => '{"a":1}']],
            new Response(303, ['Location' => '/done']),
            new Response(200),
        );

        $this->assertSame('GET', $this->outboundRequests[1]['method']);
        $this->assertNull($this->outboundRequests[1]['json']);
    }

    public function test_307_keeps_method_and_body(): void
    {
        $this->check(
            ['options' => ['method' => 'PUT'], 'secrets' => ['body' => '{"a":1}']],
            new Response(307, ['Location' => '/again']),
            new Response(200),
        );

        $this->assertSame('PUT', $this->outboundRequests[1]['method']);
        $this->assertSame(['a' => 1], $this->outboundRequests[1]['json']);
    }

    public function test_same_origin_redirect_keeps_credentials(): void
    {
        $this->check(
            ['secrets' => ['headers' => ['X-Api-Key' => 'abc'], 'auth' => ['type' => 'bearer', 'token' => 't']]],
            new Response(302, ['Location' => '/next']),
            new Response(200),
        );

        $this->assertSame('abc', $this->outboundRequests[1]['headers']['X-Api-Key']);
        $this->assertSame('Bearer t', $this->outboundRequests[1]['headers']['Authorization']);
    }

    public function test_cross_origin_redirect_drops_custom_headers_and_credentials(): void
    {
        $this->check(
            ['secrets' => ['headers' => ['X-Api-Key' => 'abc'], 'auth' => ['type' => 'bearer', 'token' => 't']]],
            new Response(302, ['Location' => 'https://other.example.net/']),
            new Response(200),
        );

        $this->assertArrayNotHasKey('X-Api-Key', $this->outboundRequests[1]['headers']);
        $this->assertArrayNotHasKey('Authorization', $this->outboundRequests[1]['headers']);
    }

    public function test_latency_threshold_marks_only_slow_successes(): void
    {
        $now = now()->toImmutable();
        $slow = new CheckOutcome($now, $now, 900, 200, true, null, null);
        $failed = new CheckOutcome($now, $now, 900, 500, false, CheckErrorType::HttpStatus, 'HTTP 500');

        $degraded = $slow->withLatencyThreshold(500);
        $this->assertTrue($degraded->isDegraded);
        $this->assertSame(WarningType::SlowResponse, $degraded->warningType);
        $this->assertStringContainsString('900', $degraded->warningMessage);
        $this->assertFalse($slow->withLatencyThreshold(900)->isDegraded);
        $this->assertFalse($slow->withLatencyThreshold(null)->isDegraded);
        $this->assertFalse($failed->withLatencyThreshold(500)->isDegraded);
    }
}
