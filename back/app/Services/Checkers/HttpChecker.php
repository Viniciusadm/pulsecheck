<?php

namespace App\Services\Checkers;

use App\Enums\CheckErrorType;
use App\Models\Monitor;
use App\Rules\HttpHeaderNames;
use App\Rules\SafeUrl;
use App\Rules\StatusCodeRange;
use App\Security\Exceptions\DeadlineExceededException;
use App\Security\Exceptions\TooManyRedirectsException;
use App\Security\Exceptions\UnresolvableHostException;
use App\Security\Exceptions\UnsafeUrlException;
use App\Security\SafeHttpClient;
use App\Security\SafeHttpRequest;
use App\Services\CheckOutcome;
use App\Support\StatusCodeMatcher;
use Carbon\CarbonImmutable;
use GuzzleHttp\Exception\ConnectTimeoutException;
use GuzzleHttp\Exception\NetworkTimeoutException;
use GuzzleHttp\Exception\ResponseTimeoutException;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class HttpChecker implements MonitorChecker
{
    public const METHODS = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'];

    public const METHODS_WITHOUT_BODY = ['GET', 'HEAD'];

    public const AUTH_TYPES = ['none', 'basic', 'bearer'];

    public const MAX_HEADERS = 20;

    private const RESERVED_HEADERS = ['host', 'content-length', 'content-type', 'transfer-encoding', 'connection', 'user-agent'];

    private const NO_LINE_BREAKS = 'regex:/^[^\r\n\x00]*$/D';

    private const MAX_ERROR_MESSAGE_LENGTH = 255;

    private const TIMEOUT_ERRNO = 28;

    private const DNS_ERRNO = 6;

    public function __construct(private SafeHttpClient $client) {}

    public function rules(): array
    {
        return [
            'url' => ['required', 'string', new SafeUrl],
            'options' => ['sometimes', 'array:method,follow_redirects,expected_status_codes,body_content_type'],
            'options.method' => ['sometimes', 'string', Rule::in(self::METHODS)],
            'options.follow_redirects' => ['sometimes', 'boolean'],
            'options.expected_status_codes' => ['sometimes', 'array', 'min:1', 'max:20'],
            'options.expected_status_codes.*' => ['string', new StatusCodeRange],
            'options.body_content_type' => ['sometimes', 'nullable', 'string', 'max:100', 'regex:/^[\w.+-]+\/[\w.+-]+(;\s*[\w.+-]+=[\w.+"-]+)?$/D'],
            'secrets' => ['sometimes', 'array:headers,body,auth'],
            'secrets.headers' => ['sometimes', 'array', 'max:'.self::MAX_HEADERS, new HttpHeaderNames(self::RESERVED_HEADERS)],
            'secrets.headers.*' => ['required', 'string', 'max:1000', self::NO_LINE_BREAKS],
            'secrets.body' => ['sometimes', 'string', 'max:'.(int) config('pulsecheck.max_request_body_bytes')],
            'secrets.auth' => ['sometimes', 'array:type,username,password,token'],
            'secrets.auth.type' => ['required_with:secrets.auth', 'string', Rule::in(self::AUTH_TYPES)],
            'secrets.auth.username' => ['required_if:secrets.auth.type,basic', 'nullable', 'string', 'max:255', 'regex:/^[^:\r\n\x00]*$/D'],
            'secrets.auth.password' => ['nullable', 'string', 'max:255', self::NO_LINE_BREAKS],
            'secrets.auth.token' => ['required_if:secrets.auth.type,bearer', 'nullable', 'string', 'max:2048', self::NO_LINE_BREAKS],
        ];
    }

    public function secretKeys(): array
    {
        return ['headers', 'body', 'auth'];
    }

    public function defaultOptions(): array
    {
        return [
            'method' => 'GET',
            'follow_redirects' => true,
            'expected_status_codes' => StatusCodeMatcher::DEFAULT_RANGES,
            'body_content_type' => null,
        ];
    }

    public function check(Monitor $monitor): CheckOutcome
    {
        return $this->perform($monitor)[0];
    }

    public function perform(Monitor $monitor, int $maxBodyBytes = 0): array
    {
        $startedAt = CarbonImmutable::now();
        $startedAtNs = hrtime(true);

        try {
            $response = $this->client->send($this->buildRequest($monitor, $maxBodyBytes));
        } catch (Throwable $exception) {
            [$errorType, $errorMessage] = $this->describeFailure($exception, $monitor);

            return [new CheckOutcome($startedAt, CarbonImmutable::now(), null, null, false, $errorType, $errorMessage), null];
        }

        $latencyMs = (int) round((hrtime(true) - $startedAtNs) / 1e6);
        $isSuccess = StatusCodeMatcher::fromList($monitor->option('expected_status_codes'))->matches($response->statusCode);

        $outcome = new CheckOutcome(
            $startedAt,
            CarbonImmutable::now(),
            $latencyMs,
            $response->statusCode,
            $isSuccess,
            $isSuccess ? null : CheckErrorType::HttpStatus,
            $isSuccess ? null : "HTTP {$response->statusCode}",
        );

        return [$outcome, $response];
    }

    private function buildRequest(Monitor $monitor, int $maxBodyBytes): SafeHttpRequest
    {
        $method = (string) $monitor->option('method');
        $body = in_array($method, self::METHODS_WITHOUT_BODY, true) ? null : $monitor->secret('body');
        $headers = $monitor->secret('headers') ?? [];

        if ($body !== null && $monitor->option('body_content_type') !== null) {
            $headers['Content-Type'] = $monitor->option('body_content_type');
        }

        $authorization = $this->authorizationHeader($monitor->secret('auth'));

        if ($authorization !== null) {
            $headers['Authorization'] = $authorization;
        }

        return new SafeHttpRequest(
            url: $monitor->url,
            method: $method,
            headers: $headers,
            body: $body,
            followRedirects: (bool) $monitor->option('follow_redirects'),
            timeout: $monitor->timeout_seconds,
            connectTimeout: $monitor->timeout_seconds,
            maxBodyBytes: $maxBodyBytes,
        );
    }

    private function authorizationHeader(?array $auth): ?string
    {
        return match ($auth['type'] ?? 'none') {
            'basic' => 'Basic '.base64_encode(($auth['username'] ?? '').':'.($auth['password'] ?? '')),
            'bearer' => 'Bearer '.($auth['token'] ?? ''),
            default => null,
        };
    }

    private function describeFailure(Throwable $exception, Monitor $monitor): array
    {
        return match (true) {
            $exception instanceof UnsafeUrlException => [CheckErrorType::BlockedDestination, 'The destination address is not allowed.'],
            $exception instanceof UnresolvableHostException => [CheckErrorType::Dns, 'The host could not be resolved.'],
            $exception instanceof TooManyRedirectsException => [CheckErrorType::TooManyRedirects, 'Too many redirects.'],
            $exception instanceof DeadlineExceededException => [CheckErrorType::Timeout, 'The request did not finish within the timeout.'],
            $exception instanceof TransferException => $this->describeTransferFailure($exception),
            default => $this->describeUnexpectedFailure($exception, $monitor),
        };
    }

    private function describeTransferFailure(TransferException $exception): array
    {
        $errno = preg_match('/cURL error (\d+):/', $exception->getMessage(), $matches) ? (int) $matches[1] : null;

        $errorType = match (true) {
            $this->isTimeout($exception) || $errno === self::TIMEOUT_ERRNO => CheckErrorType::Timeout,
            $errno === self::DNS_ERRNO => CheckErrorType::Dns,
            default => CheckErrorType::Connection,
        };

        return [$errorType, $this->sanitize($exception->getMessage())];
    }

    private function isTimeout(TransferException $exception): bool
    {
        return $exception instanceof ConnectTimeoutException
            || $exception instanceof NetworkTimeoutException
            || $exception instanceof ResponseTimeoutException;
    }

    private function describeUnexpectedFailure(Throwable $exception, Monitor $monitor): array
    {
        Log::warning('Unexpected error while checking a monitor.', [
            'monitor_id' => $monitor->id,
            'exception' => $exception::class,
        ]);

        return [CheckErrorType::Unknown, 'Unexpected error while checking the URL.'];
    }

    private function sanitize(string $message): string
    {
        $message = preg_replace('/^cURL error \d+:\s*/', '', $message);
        $message = preg_replace('#\s*\(see https?://[^)]*\)#i', '', $message);
        $message = preg_replace('#\s+for\s+https?://\S+$#i', '', $message);
        $message = preg_replace('#(https?://[^\s?\#]*)[?\#]\S*#i', '$1', $message);
        $message = preg_replace('/\s+/', ' ', trim($message));

        return Str::limit($message, self::MAX_ERROR_MESSAGE_LENGTH - 3, '...');
    }
}
