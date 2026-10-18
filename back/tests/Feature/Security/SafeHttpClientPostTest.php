<?php

namespace Tests\Feature\Security;

use App\Security\Exceptions\UnsafeUrlException;
use App\Security\SafeHttpClient;
use GuzzleHttp\Psr7\Response;
use Tests\Concerns\FakesDns;
use Tests\Concerns\FakesOutboundHttp;
use Tests\TestCase;

class SafeHttpClientPostTest extends TestCase
{
    use FakesDns;
    use FakesOutboundHttp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeDns([
            'hooks.example.com' => ['93.184.215.14'],
            'internal.example.com' => ['10.0.0.5'],
            'metadata.example.com' => ['169.254.169.254'],
        ]);
    }

    public function test_posts_json_pinned_to_the_validated_ip_without_following_redirects(): void
    {
        $this->fakeOutboundHttp(new Response(302, ['Location' => 'http://169.254.169.254/']));

        $response = app(SafeHttpClient::class)->post('https://hooks.example.com/in', ['event' => 'test'], ['Authorization' => 'Bearer abc']);

        $this->assertSame(302, $response->statusCode);
        $this->assertCount(1, $this->outboundRequests);

        $request = $this->outboundRequests[0];
        $this->assertSame('POST', $request['method']);
        $this->assertSame(['event' => 'test'], $request['json']);
        $this->assertSame('application/json', $request['headers']['Content-Type']);
        $this->assertSame('Bearer abc', $request['headers']['Authorization']);
        $this->assertSame(SafeHttpClient::USER_AGENT, $request['headers']['User-Agent']);
        $this->assertSame(['hooks.example.com:443:93.184.215.14'], $request['options']['curl'][CURLOPT_RESOLVE]);
        $this->assertFalse($request['options']['allow_redirects']);
        $this->assertSame('', $request['options']['proxy']);
    }

    public function test_user_headers_cannot_override_the_user_agent(): void
    {
        $this->fakeOutboundHttp(new Response(200));

        app(SafeHttpClient::class)->post('https://hooks.example.com/in', [], ['User-Agent' => 'evil']);

        $this->assertSame(SafeHttpClient::USER_AGENT, $this->outboundRequests[0]['headers']['User-Agent']);
    }

    public function test_private_and_metadata_destinations_are_blocked_before_any_request(): void
    {
        $this->fakeOutboundHttp();

        foreach (['https://internal.example.com/', 'https://metadata.example.com/', 'http://127.0.0.1/', 'http://[::1]/'] as $url) {
            try {
                app(SafeHttpClient::class)->post($url, []);
                $this->fail("Expected {$url} to be blocked.");
            } catch (UnsafeUrlException) {
            }
        }

        $this->assertSame([], $this->outboundRequests);
    }
}
