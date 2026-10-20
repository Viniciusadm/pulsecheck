<?php

namespace App\Services\Checkers;

use App\Enums\CheckErrorType;
use App\Enums\WarningType;
use App\Models\Monitor;
use App\Rules\SafeUrl;
use App\Security\Exceptions\SocketProbeException;
use App\Security\SafeSocketConnector;
use App\Services\Checkers\Concerns\DescribesEndpointFailures;
use App\Services\CheckOutcome;
use Carbon\CarbonImmutable;
use Throwable;

class SslCertificateChecker implements MonitorChecker
{
    use DescribesEndpointFailures;

    private const MIN_RETRY_SECONDS = 0.5;

    private const HTTPS_PORT = 443;

    public function __construct(private SafeSocketConnector $connector) {}

    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'starts_with:https://', new SafeUrl],
            'options' => ['sometimes', 'array:warning_days'],
            'options.warning_days' => ['sometimes', 'integer', 'between:1,365'],
        ];
    }

    public function secretKeys(): array
    {
        return [];
    }

    public function defaultOptions(): array
    {
        return ['warning_days' => 14];
    }

    public function check(Monitor $monitor): CheckOutcome
    {
        $startedAt = CarbonImmutable::now();
        $startedAtNs = hrtime(true);
        $host = (string) parse_url((string) $monitor->url, PHP_URL_HOST);
        $host = trim($host, '[]');
        $port = (int) (parse_url((string) $monitor->url, PHP_URL_PORT) ?? self::HTTPS_PORT);

        try {
            $certificate = $this->connector->certificate($host, $port, $monitor->timeout_seconds);
        } catch (SocketProbeException $exception) {
            return $exception->kind === SocketProbeException::TLS
                ? $this->invalidCertificateOutcome($startedAt, $exception, $monitor, $host, $port, $monitor->timeout_seconds - (hrtime(true) - $startedAtNs) / 1e9)
                : $this->failedOutcome($startedAt, $exception, $monitor);
        } catch (Throwable $exception) {
            return $this->failedOutcome($startedAt, $exception, $monitor);
        }

        $latencyMs = (int) round((hrtime(true) - $startedAtNs) / 1e6);
        $expiresAt = $this->expiresAt($certificate);

        if ($expiresAt === null) {
            return new CheckOutcome($startedAt, CarbonImmutable::now(), $latencyMs, null, false, CheckErrorType::Tls, 'The server did not present a certificate.');
        }

        $daysLeft = (int) floor(CarbonImmutable::now()->diffInSeconds($expiresAt, false) / 86400);
        $isExpiring = $daysLeft <= (int) $monitor->option('warning_days');

        return new CheckOutcome(
            $startedAt,
            CarbonImmutable::now(),
            $latencyMs,
            null,
            true,
            null,
            null,
            $isExpiring,
            $isExpiring ? WarningType::CertificateExpiring : null,
            $isExpiring ? __('The certificate expires in :days days.', ['days' => max(0, $daysLeft)]) : null,
            $expiresAt,
        );
    }

    private function invalidCertificateOutcome(CarbonImmutable $startedAt, SocketProbeException $exception, Monitor $monitor, string $host, int $port, float $remainingSeconds): CheckOutcome
    {
        try {
            $expiresAt = $remainingSeconds >= self::MIN_RETRY_SECONDS
                ? $this->expiresAt($this->connector->certificate($host, $port, $remainingSeconds, verify: false))
                : null;
        } catch (Throwable) {
            $expiresAt = null;
        }

        if ($expiresAt !== null && $expiresAt->isPast()) {
            return new CheckOutcome(
                $startedAt,
                CarbonImmutable::now(),
                null,
                null,
                false,
                CheckErrorType::CertificateExpired,
                __('The certificate expired on :date.', ['date' => $expiresAt->toDateString()]),
                certificateExpiresAt: $expiresAt,
            );
        }

        return $this->failedOutcome($startedAt, $exception, $monitor);
    }

    private function expiresAt(?array $certificate): ?CarbonImmutable
    {
        $validTo = $certificate['validTo_time_t'] ?? null;

        return is_int($validTo) ? CarbonImmutable::createFromTimestampUTC($validTo) : null;
    }
}
