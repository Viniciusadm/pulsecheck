<?php

namespace App\Console\Commands;

use App\Jobs\RunMonitorCheck;
use App\Models\Monitor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

#[Signature('monitors:dispatch-due')]
#[Description('Queue a check for every active monitor whose next check is due')]
class DispatchDueMonitors extends Command
{
    private const CHUNK_SIZE = 500;

    public function handle(): int
    {
        $now = now();
        $dispatched = 0;

        Monitor::query()
            ->select(['id', 'interval_minutes', 'timeout_seconds'])
            ->where('is_paused', false)
            ->where('next_check_at', '<=', $now)
            ->chunkById(self::CHUNK_SIZE, function (Collection $monitors) use ($now, &$dispatched): void {
                foreach ($monitors->groupBy('interval_minutes') as $intervalMinutes => $group) {
                    Monitor::query()
                        ->whereKey($group->modelKeys())
                        ->where('is_paused', false)
                        ->where('next_check_at', '<=', $now)
                        ->update(['next_check_at' => $now->copy()->startOfMinute()->addMinutes((int) $intervalMinutes)]);
                }

                foreach ($monitors as $monitor) {
                    RunMonitorCheck::dispatch($monitor);
                    $dispatched++;
                }
            });

        $this->components->info("Dispatched {$dispatched} monitor check(s).");

        return self::SUCCESS;
    }
}
