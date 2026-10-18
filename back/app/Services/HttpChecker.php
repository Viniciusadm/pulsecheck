<?php

namespace App\Services;

use App\Enums\CheckErrorType;
use App\Models\Monitor;
use App\Security\Exceptions\DeadlineExceededException;
use App\Security\Exceptions\TooManyRedirectsException;
use App\Security\Exceptions\UnresolvableHostException;
use App\Security\Exceptions\UnsafeUrlException;
use App\Security\SafeHttpClient;
use Carbon\CarbonImmutable;
use GuzzleHttp\Exception\ConnectTimeoutException;
use GuzzleHttp\Exception\NetworkTimeoutException;
use GuzzleHttp\Exception\ResponseTimeoutException;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class HttpChecker
{
    private const MAX_ERROR_MESSAGE_LENGTH = 255;

    private const TIMEOUT_ERRNO = 28;

    private const DNS_ERRNO = 6;

    public function __construct(private SafeHttpClient $client) {}

    public function check(Monitor $monitor): CheckOutcome
    {
        $startedAt = CarbonImmutable::now();
        $startedAtNs = hrtime(true);

        try {
            $response = $this->client->get($monitor->url, $monitor->timeout_seconds, $monitor->timeout_seconds);
        } catch (Throwable $exception) {
            [$errorType, $errorMessage] = $this->describeFailure($exception, $monitor);

            return new CheckOutcome($startedAt, CarbonImmutable::now(), null, null, false, $errorType, $errorMessage);
        }

        $latencyMs = (int) round((hrtime(true) - $startedAtNs) / 1e6);
        $isSuccess = $response->statusCode >= 200 && $response->statusCode < 400;

        return new CheckOutcome(
            $startedAt,
            CarbonImmutable::now(),
            $latencyMs,
            $response->statusCode,
            $isSuccess,
            $isSuccess ? null : CheckErrorType::HttpStatus,
            $isSuccess ? null : "HTTP {$response->statusCode}",
        );
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
