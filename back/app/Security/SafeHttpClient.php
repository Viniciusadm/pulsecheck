<?php

namespace App\Security;

use App\Security\Exceptions\DeadlineExceededException;
use App\Security\Exceptions\TooManyRedirectsException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;

class SafeHttpClient
{
    public const USER_AGENT = 'PulseCheck/1.0 (+uptime monitor)';

    private const REDIRECT_STATUSES = [301, 302, 303, 307, 308];

    private Client $client;

    public function __construct(private UrlGuard $guard, ?callable $handler = null)
    {
        $this->client = new Client([
            'handler' => HandlerStack::create($handler ?? new CurlHandler),
        ]);
    }

    public function get(string $url, float $timeout = 10, float $connectTimeout = 5): SafeHttpResponse
    {
        $maxRedirects = (int) config('pulsecheck.max_redirects');
        $redirects = 0;
        $deadline = hrtime(true) + (int) ($timeout * 1e9);

        while (true) {
            $target = $this->guard->inspect($url);

            $remainingSeconds = ($deadline - hrtime(true)) / 1e9;

            if ($remainingSeconds <= 0) {
                throw new DeadlineExceededException;
            }

            $options = $this->requestOptions($target, $remainingSeconds, min($connectTimeout, $remainingSeconds));
            $response = $this->client->request('GET', $target->url, $options);
            $response->getBody()->close();

            $location = $response->getHeaderLine('Location');

            if (! in_array($response->getStatusCode(), self::REDIRECT_STATUSES, true) || $location === '') {
                return new SafeHttpResponse($response->getStatusCode(), $target->url, $redirects);
            }

            if ($redirects >= $maxRedirects) {
                throw new TooManyRedirectsException($maxRedirects);
            }

            $url = (string) UriResolver::resolve(new Uri($target->url), new Uri($location));
            $redirects++;
        }
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
