<?php

namespace App\Services\Checkers;

use App\Enums\CheckErrorType;
use App\Models\Monitor;
use App\Services\CheckOutcome;
use Carbon\CarbonImmutable;

class HeartbeatChecker implements MonitorChecker
{
    public function rules(): array
    {
        return [
            'url' => ['prohibited'],
            'options' => ['sometimes', 'array:grace_seconds'],
            'options.grace_seconds' => ['sometimes', 'integer', 'between:0,3600'],
        ];
    }

    public function secretKeys(): array
    {
        return [];
    }

    public function defaultOptions(): array
    {
        return ['grace_seconds' => 60];
    }

    public function check(Monitor $monitor): ?CheckOutcome
    {
        $now = CarbonImmutable::now();

        if ($monitor->last_heartbeat_at !== null && $monitor->last_heartbeat_at->greaterThan($now->subSeconds($this->windowSeconds($monitor)))) {
            return null;
        }

        return new CheckOutcome(
            $now,
            $now,
            null,
            null,
            false,
            CheckErrorType::HeartbeatMissed,
            $monitor->last_heartbeat_at === null
                ? __('No ping was received yet.')
                : __('No ping was received since :time.', ['time' => $monitor->last_heartbeat_at->utc()->toIso8601ZuluString()]),
        );
    }

    public function windowSeconds(Monitor $monitor): int
    {
        return $monitor->interval_minutes * 60 + (int) $monitor->option('grace_seconds');
    }
}
