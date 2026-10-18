<?php

namespace App\Console\Commands;

use App\Actions\RollUpDailyUptime;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('uptime:rollup {--days=2 : Number of closed UTC days to aggregate}')]
#[Description('Aggregate check results of closed days into daily uptime rows')]
class RollUpUptime extends Command
{
    public function handle(RollUpDailyUptime $rollUpDailyUptime): int
    {
        $days = max(1, (int) $this->option('days'));
        $rows = $rollUpDailyUptime->handle($days);

        $this->components->info("Rolled up {$rows} daily uptime row(s).");

        return self::SUCCESS;
    }
}
