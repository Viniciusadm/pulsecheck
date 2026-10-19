<?php

namespace App\Services\Checkers\Concerns;

use App\Enums\CheckErrorType;
use App\Models\Monitor;
use App\Security\Exceptions\SocketProbeException;
use App\Security\Exceptions\UnresolvableHostException;
use App\Security\Exceptions\UnsafeUrlException;
use App\Services\CheckOutcome;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

trait DescribesEndpointFailures
{
    protected function failedOutcome(CarbonImmutable $startedAt, Throwable $exception, Monitor $monitor): CheckOutcome
    {
        [$errorType, $errorMessage] = match (true) {
            $exception instanceof UnsafeUrlException => [CheckErrorType::BlockedDestination, 'The destination address is not allowed.'],
            $exception instanceof UnresolvableHostException => [CheckErrorType::Dns, 'The host could not be resolved.'],
            $exception instanceof SocketProbeException => [$this->socketErrorType($exception), Str::limit($exception->getMessage(), 252, '...')],
            default => $this->unexpectedFailure($exception, $monitor),
        };

        return new CheckOutcome($startedAt, CarbonImmutable::now(), null, null, false, $errorType, $errorMessage);
    }

    private function socketErrorType(SocketProbeException $exception): CheckErrorType
    {
        return match ($exception->kind) {
            SocketProbeException::TIMEOUT => CheckErrorType::Timeout,
            SocketProbeException::TLS => CheckErrorType::Tls,
            default => CheckErrorType::Connection,
        };
    }

    private function unexpectedFailure(Throwable $exception, Monitor $monitor): array
    {
        Log::warning('Unexpected error while checking a monitor.', [
            'monitor_id' => $monitor->id,
            'exception' => $exception::class,
        ]);

        return [CheckErrorType::Unknown, 'Unexpected error while checking the monitor.'];
    }
}
