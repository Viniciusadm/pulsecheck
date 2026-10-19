<?php

namespace Tests\Feature\Security;

use App\Security\SafeHttpClient;
use App\Security\SafeHttpRequest;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SafeHttpClientBodyLimitTest extends TestCase
{
    private Process $server;

    private string $routerPath;

    private string $baseUrl;

    protected function setUp(): void
    {
        parent::setUp();

        config(['pulsecheck.allow_private_targets' => true]);

        $this->routerPath = tempnam(sys_get_temp_dir(), 'endless-body').'.php';
        file_put_contents($this->routerPath, <<<'PHP'
            <?php
            header('Content-Type: text/plain');
            while (! connection_aborted()) {
                echo str_repeat('a', 65536);
                flush();
                usleep(20000);
            }
            PHP);

        $probe = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($probe, false);
        fclose($probe);

        $this->baseUrl = "http://{$address}";
        $this->server = new Process([PHP_BINARY, '-S', $address, $this->routerPath]);
        $this->server->start();

        $deadline = microtime(true) + 5;

        while (@fsockopen('127.0.0.1', (int) parse_url($this->baseUrl, PHP_URL_PORT)) === false && microtime(true) < $deadline) {
            usleep(50000);
        }
    }

    protected function tearDown(): void
    {
        $this->server->stop(0);
        @unlink($this->routerPath);

        parent::tearDown();
    }

    public function test_download_stops_once_the_body_limit_is_reached(): void
    {
        $startedAt = microtime(true);

        $response = app(SafeHttpClient::class)->send(new SafeHttpRequest("{$this->baseUrl}/", timeout: 10, maxBodyBytes: 1024));

        $this->assertLessThan(5, microtime(true) - $startedAt);
        $this->assertSame(200, $response->statusCode);
        $this->assertTrue($response->bodyTruncated);
        $this->assertSame(str_repeat('a', 1024), $response->body);
    }

    public function test_download_stops_after_the_headers_when_the_body_is_not_needed(): void
    {
        $startedAt = microtime(true);

        $response = app(SafeHttpClient::class)->send(new SafeHttpRequest("{$this->baseUrl}/", timeout: 10));

        $this->assertLessThan(5, microtime(true) - $startedAt);
        $this->assertSame(200, $response->statusCode);
        $this->assertNull($response->body);
    }
}
