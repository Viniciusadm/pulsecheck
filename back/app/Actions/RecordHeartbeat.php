<?php

namespace App\Actions;

use App\Models\Monitor;
use App\Services\CheckOutcome;
use Carbon\CarbonImmutable;

class RecordHeartbeat
{
    public function __construct(private RecordCheckResult $recordCheckResult) {}

    public function handle(Monitor $monitor): bool
    {
        if ($monitor->is_paused) {
            return false;
        }

        $now = CarbonImmutable::now();

        Monitor::query()->whereKey($monitor->id)->update([
            'last_heartbeat_at' => $now,
            'next_check_at' => $monitor->initialCheckAt(),
        ]);

        $this->recordCheckResult->handle($monitor, new CheckOutcome($now, $now, null, null, true, null, null));

        return true;
    }
}
