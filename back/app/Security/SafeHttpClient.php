<?php

namespace App\Security;

use App\Security\Exceptions\DeadlineExceededException;
use App\Security\Exceptions\TooManyRedirectsException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ResponseException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class SafeHttpClient
{
    public const USER_AGENT = 'PulseCheck/1.0 (+uptime monitor)';

    private const REDIRECT_STATUSES = [301, 302, 303, 307, 308];

    private const READ_CHUNK_BYTES = 8192;

    private Client $client;

    public function __construct(private UrlGuard $guard, ?callable $handler = null)
    {
        $this->client = new Client([
            'handler' => HandlerStack::create($handler ?? new CurlHandler),
        ]);
    }

    public function get(string $url, float $timeout = 10, float $connectTimeout = 5): SafeHttpResponse
    {
        return $this->send(new SafeHttpRequest($url, timeout: $timeout, connectTimeout: $connectTimeout));
    }

    public function send(SafeHttpRequest $request): SafeHttpResponse
    {
        $maxRedirects = (int) config('pulsecheck.max_redirects');
        $redirects = 0;
        $deadline = hrtime(true) + (int) ($request->timeout * 1e9);
        $originalOrigin = $this->origin($request->url);
        $url = $request->url;
        $method = $request->method;
        $headers = $request->headers;
        $body = $request->body;

        while (true) {
            $target = $this->guard->inspect($url);

            $remainingSeconds = ($deadline - hrtime(true)) / 1e9;

            if ($remainingSeconds <= 0) {
                throw new DeadlineExceededException;
            }

            $options = $this->requestOptions($target, $remainingSeconds, min($request->connectTimeout, $remainingSeconds), $headers);

            if ($body !== null) {
                $options['body'] = $body;
            }

            $response = $this->requestWithBodyLimit($method, $target->url, $options, $request->maxBodyBytes);
            $location = $response->getHeaderLine('Location');
            $isRedirect = in_array($response->getStatusCode(), self::REDIRECT_STATUSES, true) && $location !== '';

            if (! $request->followRedirects || ! $isRedirect) {
                [$responseBody, $truncated] = $this->readBody($response->getBody(), $request->maxBodyBytes);

                return new SafeHttpResponse($response->getStatusCode(), $target->url, $redirects, $responseBody, $truncated);
            }

            $response->getBody()->close();

            if ($redirects >= $maxRedirects) {
                throw new TooManyRedirectsException($maxRedirects);
            }

            $url = (string) UriResolver::resolve(new Uri($target->url), new Uri($location));
            $redirects++;

            if ($this->redirectSwitchesToGet($response->getStatusCode(), $method)) {
                $method = $method === 'HEAD' ? 'HEAD' : 'GET';
                $body = null;
                $headers = array_filter($headers, fn (string $name) => strtolower($name) !== 'content-type', ARRAY_FILTER_USE_KEY);
            }

            if ($this->origin($url) !== $originalOrigin) {
                $headers = [];
            }
        }
    }

    private function requestWithBodyLimit(string $method, string $url, array $options, int $maxBodyBytes): ResponseInterface
    {
        $bodyLimitReached = false;
        $options['progress'] = function (int $downloadSize, int $downloaded) use ($maxBodyBytes, &$bodyLimitReached): bool {
            return $bodyLimitReached = $downloaded > $maxBodyBytes;
        };

        try {
            return $this->client->request($method, $url, $options);
        } catch (ResponseException $exception) {
            if (! $bodyLimitReached) {
                throw $exception;
            }

            return $exception->getResponse();
        }
    }

    private function redirectSwitchesToGet(int $statusCode, string $method): bool
    {
        return $statusCode === 303 || (in_array($statusCode, [301, 302], true) && $method === 'POST');
    }

    private function origin(string $url): string
    {
        $uri = new Uri($url);

        return strtolower($uri->getScheme()).'://'.strtolower($uri->getHost()).':'.($uri->getPort() ?? ($uri->getScheme() === 'https' ? 443 : 80));
    }

    private function readBody(StreamInterface $stream, int $maxBytes): array
    {
        if ($maxBytes <= 0) {
            $stream->close();

            return [null, false];
        }

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        $body = '';

        while (! $stream->eof() && strlen($body) <= $maxBytes) {
            $body .= $stream->read(min(self::READ_CHUNK_BYTES, $maxBytes + 1 - strlen($body)));
        }

        $stream->close();

        $truncated = strlen($body) > $maxBytes;

        return [$truncated ? substr($body, 0, $maxBytes) : $body, $truncated];
    }

    public function post(string $url, array $json, array $headers = [], float $timeout = 10, float $connectTimeout = 5): SafeHttpResponse
    {
        $target = $this->guard->inspect($url);

        $options = $this->requestOptions($target, $timeout, $connectTimeout, [
            ...$headers,
            'Content-Type' => 'application/json',
        ]);
        $options['body'] = json_encode($json, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $response = $this->client->request('POST', $target->url, $options);
        $response->getBody()->close();

        return new SafeHttpResponse($response->getStatusCode(), $target->url, 0);
    }

    private function requestOptions(ValidatedTarget $target, float $timeout, float $connectTimeout, array $headers = []): array
    {
        $curlOptions = [];

        if (! $target->isIpLiteral) {
            $pinnedIp = $target->ips[0];
            $address = str_contains($pinnedIp, ':') ? "[{$pinnedIp}]" : $pinnedIp;
            $curlOptions[CURLOPT_RESOLVE] = ["{$target->host}:{$target->port}:{$address}"];
        }

        return [
            'allow_redirects' => false,
            'http_errors' => false,
            'stream' => true,
            'proxy' => '',
            'protocols' => ['http', 'https'],
            'timeout' => $timeout,
            'connect_timeout' => $connectTimeout,
            'headers' => [
                'Accept' => '*/*',
                ...$headers,
                'User-Agent' => self::USER_AGENT,
            ],
            'curl' => $curlOptions,
        ];
    }
}
