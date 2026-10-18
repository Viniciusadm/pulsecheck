<?php

namespace Tests\Feature\Security;

use App\Enums\CheckErrorType;
use App\Security\Exceptions\DeadlineExceededException;
use App\Security\Exceptions\TooManyRedirectsException;
use App\Security\Exceptions\UnsafeUrlException;
use App\Security\SafeHttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\FakesDns;
use Tests\TestCase;

class SafeHttpClientSsrfTest extends TestCase
{
    use FakesDns;

    private array $sentRequests = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeDns([
            'example.com' => ['93.184.215.14'],
            'www.example.com' => ['93.184.215.15'],
            'v6.example.com' => ['2606:2800:21f:cb07:6820:80da:af6b:8b2c'],
            'internal.example.com' => ['10.0.0.5'],
        ]);
    }

    private function client(Response ...$responses): SafeHttpClient
    {
        $queue = array_map(fn (Response $response) => function (RequestInterface $request, array $options) use ($response) {
            $this->sentRequests[] = ['url' => (string) $request->getUri(), 'host' => $request->getHeaderLine('Host'), 'user_agent' => $request->getHeaderLine('User-Agent'), 'options' => $options];

            return $response;
        }, $responses);

        return $this->app->make(SafeHttpClient::class, ['handler' => new MockHandler($queue)]);
    }

    public function test_connection_is_pinned_to_the_validated_ip_without_proxy_or_automatic_redirects(): void
    {
        $response = $this->client(new Response(200))->get('https://example.com/health', timeout: 7, connectTimeout: 3);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('https://example.com/health', $response->finalUrl);
        $this->assertCount(1, $this->sentRequests);

        $options = $this->sentRequests[0]['options'];
        $this->assertSame(['example.com:443:93.184.215.14'], $options['curl'][CURLOPT_RESOLVE]);
        $this->assertSame(['http', 'https'], $options['protocols']);
        $this->assertSame('', $options['proxy']);
        $this->assertFalse($options['allow_redirects']);
        $this->assertTrue($options['stream']);
        $this->assertFalse($options['http_errors']);
        $this->assertEqualsWithDelta(7, $options['timeout'], 0.5);
        $this->assertLessThanOrEqual(7, $options['timeout']);
        $this->assertEqualsWithDelta(3, $options['connect_timeout'], 0.5);
        $this->assertSame('example.com', $this->sentRequests[0]['host']);
    }

    public function test_ipv6_addresses_are_pinned_in_brackets(): void
    {
        $this->client(new Response(204))->get('http://v6.example.com:8080/');

        $this->assertSame(['v6.example.com:8080:[2606:2800:21f:cb07:6820:80da:af6b:8b2c]'], $this->sentRequests[0]['options']['curl'][CURLOPT_RESOLVE]);
    }

    public function test_follows_safe_redirects_revalidating_and_repinning_each_hop(): void
    {
        $response = $this->client(
            new Response(301, ['Location' => 'https://www.example.com/start']),
            new Response(302, ['Location' => '/final?x=1']),
            new Response(200),
        )->get('http://example.com/');

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('https://www.example.com/final?x=1', $response->finalUrl);
        $this->assertSame(2, $response->redirects);
        $this->assertSame(['example.com:80:93.184.215.14'], $this->sentRequests[0]['options']['curl'][CURLOPT_RESOLVE]);
        $this->assertSame(['www.example.com:443:93.184.215.15'], $this->sentRequests[1]['options']['curl'][CURLOPT_RESOLVE]);
        $this->assertSame(['www.example.com:443:93.184.215.15'], $this->sentRequests[2]['options']['curl'][CURLOPT_RESOLVE]);
        $this->assertSame(['example.com', 'www.example.com'], array_values(array_unique($this->dnsLookups->getArrayCopy())));
    }

    public static function forbiddenRedirectTargets(): array
    {
        return [
            'private ip literal' => ['http://10.0.0.1/admin'],
            'metadata ip' => ['http://169.254.169.254/latest/meta-data'],
            'host resolving to private ip' => ['http://internal.example.com/'],
            'localhost' => ['http://localhost:6379/'],
            'non http scheme' => ['file:///etc/passwd'],
            'obfuscated ip' => ['http://2130706433/'],
        ];
    }

    #[DataProvider('forbiddenRedirectTargets')]
    public function test_redirect_to_a_forbidden_destination_is_blocked_before_connecting(string $location): void
    {
        $client = $this->client(new Response(302, ['Location' => $location]), new Response(200));

        try {
            $client->get('https://example.com/');
            $this->fail('Expected the redirect to be blocked.');
        } catch (UnsafeUrlException $exception) {
            $this->assertSame(CheckErrorType::BlockedDestination, $exception->errorType());
        }

        $this->assertCount(1, $this->sentRequests);
    }

    public function test_forbidden_initial_url_never_connects(): void
    {
        $this->expectException(UnsafeUrlException::class);

        try {
            $this->client(new Response(200))->get('http://127.0.0.1/');
        } finally {
            $this->assertCount(0, $this->sentRequests);
        }
    }

    public function test_redirect_chains_are_limited(): void
    {
        config(['pulsecheck.max_redirects' => 2]);
        $client = $this->client(
            new Response(302, ['Location' => '/1']),
            new Response(302, ['Location' => '/2']),
            new Response(302, ['Location' => '/3']),
            new Response(200),
        );

        try {
            $client->get('https://example.com/');
            $this->fail('Expected too many redirects.');
        } catch (TooManyRedirectsException $exception) {
            $this->assertSame(CheckErrorType::TooManyRedirects, $exception->errorType());
        }

        $this->assertCount(3, $this->sentRequests);
    }

    public function test_the_timeout_is_a_total_budget_across_redirects(): void
    {
        $handler = new MockHandler([
            function (RequestInterface $request, array $options) {
                $this->sentRequests[] = ['options' => $options];
                usleep(300_000);

                return new Response(302, ['Location' => '/next']);
            },
            function (RequestInterface $request, array $options) {
                $this->sentRequests[] = ['options' => $options];

                return new Response(200);
            },
        ]);

        $this->app->make(SafeHttpClient::class, ['handler' => $handler])->get('https://example.com/', timeout: 1, connectTimeout: 1);

        $this->assertLessThanOrEqual(0.75, $this->sentRequests[1]['options']['timeout']);
        $this->assertLessThanOrEqual(0.75, $this->sentRequests[1]['options']['connect_timeout']);
    }

    public function test_an_exhausted_budget_stops_before_the_next_hop(): void
    {
        $handler = new MockHandler([
            function () {
                usleep(1_100_000);

                return new Response(302, ['Location' => '/next']);
            },
            new Response(200),
        ]);

        try {
            $this->app->make(SafeHttpClient::class, ['handler' => $handler])->get('https://example.com/', timeout: 1, connectTimeout: 1);
            $this->fail('Expected the deadline to be exceeded.');
        } catch (DeadlineExceededException $exception) {
            $this->assertSame(CheckErrorType::Timeout, $exception->errorType());
        }

        $this->assertCount(1, $handler);
    }

    public function test_redirect_without_location_is_returned_as_is(): void
    {
        $response = $this->client(new Response(302))->get('https://example.com/');

        $this->assertSame(302, $response->statusCode);
        $this->assertSame(0, $response->redirects);
    }

    public function test_sends_an_identifiable_user_agent(): void
    {
        $this->client(new Response(200))->get('https://example.com/');

        $this->assertStringStartsWith('PulseCheck/', $this->sentRequests[0]['user_agent']);
    }
}
