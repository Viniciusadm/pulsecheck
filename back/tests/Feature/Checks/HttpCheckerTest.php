<?php

namespace Tests\Feature\Checks;

use App\Enums\CheckErrorType;
use App\Models\Monitor;
use App\Security\SafeHttpClient;
use App\Services\Checkers\HttpChecker;
use App\Services\CheckOutcome;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ConnectTimeoutException;
use GuzzleHttp\Exception\NetworkException;
use GuzzleHttp\Exception\NetworkTimeoutException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\FakesDns;
use Tests\TestCase;

class HttpCheckerTest extends TestCase
{
    use FakesDns;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeDns([
            'example.com' => ['93.184.215.14'],
            'www.example.com' => ['93.184.215.15'],
        ]);
    }

    private function check(string $url, mixed ...$responses): CheckOutcome
    {
        $this->app->instance(SafeHttpClient::class, $this->app->make(SafeHttpClient::class, ['handler' => new MockHandler($responses)]));

        $monitor = Monitor::factory()->make(['url' => $url, 'timeout_seconds' => 10]);

        return $this->app->make(HttpChecker::class)->check($monitor);
    }

    private function curlError(int $errno, string $error): TransferException
    {
        $request = new Request('GET', 'https://example.com/?token=secret');
        $message = "cURL error {$errno}: {$error} (see https://curl.se/libcurl/c/libcurl-errors.html) for https://example.com/?token=secret";

        return match ($errno) {
            28 => new NetworkTimeoutException($message, $request),
            52, 55, 56 => new NetworkException($message, $request),
            default => new ConnectException($message, $request),
        };
    }

    public static function successfulStatuses(): array
    {
        return [[200], [204], [301], [304], [399]];
    }

    #[DataProvider('successfulStatuses')]
    public function test_statuses_from_200_to_399_are_successful(int $status): void
    {
        $outcome = $this->check('https://example.com', new Response($status));

        $this->assertTrue($outcome->isSuccess);
        $this->assertSame($status, $outcome->statusCode);
        $this->assertNull($outcome->errorType);
        $this->assertNull($outcome->errorMessage);
        $this->assertIsInt($outcome->latencyMs);
        $this->assertGreaterThanOrEqual(0, $outcome->latencyMs);
        $this->assertTrue($outcome->finishedAt->greaterThanOrEqualTo($outcome->startedAt));
    }

    public function test_valid_redirect_is_followed(): void
    {
        $outcome = $this->check('https://example.com', new Response(301, ['Location' => 'https://www.example.com/']), new Response(200));

        $this->assertTrue($outcome->isSuccess);
        $this->assertSame(200, $outcome->statusCode);
    }

    public static function failingStatuses(): array
    {
        return [[400], [404], [429], [500], [503]];
    }

    #[DataProvider('failingStatuses')]
    public function test_statuses_from_400_are_failures(int $status): void
    {
        $outcome = $this->check('https://example.com', new Response($status));

        $this->assertFalse($outcome->isSuccess);
        $this->assertSame($status, $outcome->statusCode);
        $this->assertSame(CheckErrorType::HttpStatus, $outcome->errorType);
        $this->assertSame("HTTP {$status}", $outcome->errorMessage);
        $this->assertIsInt($outcome->latencyMs);
    }

    public static function transportErrors(): array
    {
        return [
            'timeout' => [28, 'Operation timed out after 10001 milliseconds with 0 bytes received', CheckErrorType::Timeout],
            'dns' => [6, 'Could not resolve host: example.com', CheckErrorType::Dns],
            'connection refused' => [7, 'Failed to connect to example.com port 443: Connection refused', CheckErrorType::Connection],
            'tls' => [60, 'SSL certificate problem: certificate has expired', CheckErrorType::Connection],
            'reset' => [56, 'Recv failure: Connection reset by peer', CheckErrorType::Connection],
            'empty reply' => [52, 'Empty reply from server', CheckErrorType::Connection],
        ];
    }

    #[DataProvider('transportErrors')]
    public function test_transport_errors_are_mapped(int $errno, string $error, CheckErrorType $expected): void
    {
        $outcome = $this->check('https://example.com/?token=secret', $this->curlError($errno, $error));

        $this->assertFalse($outcome->isSuccess);
        $this->assertNull($outcome->statusCode);
        $this->assertNull($outcome->latencyMs);
        $this->assertSame($expected, $outcome->errorType);
        $this->assertSame($error, $outcome->errorMessage);
    }

    public function test_connect_timeout_is_a_timeout(): void
    {
        $outcome = $this->check('https://example.com', new ConnectTimeoutException(
            'cURL error 28: Connection timed out after 5001 milliseconds (see https://curl.se/libcurl/c/libcurl-errors.html) for https://example.com/',
            new Request('GET', 'https://example.com/'),
        ));

        $this->assertSame(CheckErrorType::Timeout, $outcome->errorType);
        $this->assertSame('Connection timed out after 5001 milliseconds', $outcome->errorMessage);
    }

    public function test_redirect_to_a_private_ip_is_a_blocked_destination(): void
    {
        $outcome = $this->check('https://example.com', new Response(302, ['Location' => 'http://10.0.0.1/']), new Response(200));

        $this->assertFalse($outcome->isSuccess);
        $this->assertSame(CheckErrorType::BlockedDestination, $outcome->errorType);
        $this->assertSame('The destination address is not allowed.', $outcome->errorMessage);
    }

    public function test_unresolvable_host_is_a_dns_error(): void
    {
        $outcome = $this->check('https://missing.example.com');

        $this->assertSame(CheckErrorType::Dns, $outcome->errorType);
        $this->assertSame('The host could not be resolved.', $outcome->errorMessage);
    }

    public function test_too_many_redirects_is_reported(): void
    {
        config(['pulsecheck.max_redirects' => 1]);

        $outcome = $this->check('https://example.com', new Response(302, ['Location' => '/a']), new Response(302, ['Location' => '/b']));

        $this->assertSame(CheckErrorType::TooManyRedirects, $outcome->errorType);
    }

    public function test_error_messages_never_contain_query_strings_and_are_truncated(): void
    {
        $outcome = $this->check(
            'https://example.com/?token=secret',
            $this->curlError(35, 'OpenSSL error for https://example.com/path?token=secret '.str_repeat('x', 600)),
        );

        $this->assertStringNotContainsString('token=secret', $outcome->errorMessage);
        $this->assertStringContainsString('https://example.com/path', $outcome->errorMessage);
        $this->assertLessThanOrEqual(255, mb_strlen($outcome->errorMessage));
    }

    public function test_unexpected_errors_are_unknown_without_details(): void
    {
        $outcome = $this->check('https://example.com', new RuntimeException('secret internal detail'));

        $this->assertSame(CheckErrorType::Unknown, $outcome->errorType);
        $this->assertSame('Unexpected error while checking the URL.', $outcome->errorMessage);
    }
}
