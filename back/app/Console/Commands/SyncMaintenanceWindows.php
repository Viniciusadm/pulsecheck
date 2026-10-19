<?php

namespace App\Console\Commands;

use App\Actions\SyncMaintenanceState;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('maintenance:sync')]
#[Description('Mark monitors entering or leaving a maintenance window')]
class SyncMaintenanceWindows extends Command
{
    public function handle(SyncMaintenanceState $syncMaintenanceState): int
    {
        $changed = $syncMaintenanceState->handle();

        $this->components->info("Updated the maintenance state of {$changed} monitor(s).");

        return self::SUCCESS;
    }
}
