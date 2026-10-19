<?php

namespace App\Services\Checkers;

use App\Models\Monitor;
use App\Rules\SafeHost;
use App\Security\SafeSocketConnector;
use App\Services\Checkers\Concerns\DescribesEndpointFailures;
use App\Services\CheckOutcome;
use Carbon\CarbonImmutable;
use Throwable;

class TcpChecker implements MonitorChecker
{
    use DescribesEndpointFailures;

    public function __construct(private SafeSocketConnector $connector) {}

    public function rules(): array
    {
        return [
            'url' => ['prohibited'],
            'options' => ['required', 'array:host,port'],
            'options.host' => ['required', 'string', 'max:253', new SafeHost],
            'options.port' => ['required', 'integer', 'between:1,65535'],
        ];
    }

    public function secretKeys(): array
    {
        return [];
    }

    public function defaultOptions(): array
    {
        return ['host' => null, 'port' => null];
    }

    public function check(Monitor $monitor): CheckOutcome
    {
        $startedAt = CarbonImmutable::now();
        $startedAtNs = hrtime(true);

        try {
            $this->connector->connect((string) $monitor->option('host'), (int) $monitor->option('port'), $monitor->timeout_seconds);
        } catch (Throwable $exception) {
            return $this->failedOutcome($startedAt, $exception, $monitor);
        }

        $latencyMs = (int) round((hrtime(true) - $startedAtNs) / 1e6);

        return new CheckOutcome($startedAt, CarbonImmutable::now(), $latencyMs, null, true, null, null);
    }
}
