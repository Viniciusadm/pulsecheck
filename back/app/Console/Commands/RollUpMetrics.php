<?php

namespace App\Console\Commands;

use App\Actions\RollUpHourlyStats;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('metrics:rollup {--hours=3 : Number of closed UTC hours to aggregate}')]
#[Description('Aggregate check results of closed hours into hourly dashboard stats')]
class RollUpMetrics extends Command
{
    public function handle(RollUpHourlyStats $rollUpHourlyStats): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $rows = $rollUpHourlyStats->handle($hours);

        $this->components->info("Rolled up {$rows} hourly stats row(s).");

        return self::SUCCESS;
    }
}
