<?php

namespace Tests\Concerns;

use App\Security\SafeHttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

trait FakesOutboundHttp
{
    protected array $outboundRequests = [];

    protected function fakeOutboundHttp(Response|\Throwable ...$responses): void
    {
        $queue = array_map(fn (Response|\Throwable $response) => function (RequestInterface $request, array $options) use ($response) {
            $this->outboundRequests[] = [
                'method' => $request->getMethod(),
                'url' => (string) $request->getUri(),
                'headers' => array_map(fn (array $values) => implode(', ', $values), $request->getHeaders()),
                'json' => json_decode((string) $request->getBody(), true),
                'options' => $options,
            ];

            if ($response instanceof \Throwable) {
                throw $response;
            }

            return $response;
        }, $responses);

        $this->app->instance(SafeHttpClient::class, $this->app->make(SafeHttpClient::class, ['handler' => new MockHandler($queue)]));
    }
}
