<?php

namespace App\Events;

use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MonitorRecovered implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Monitor $monitor,
        public Incident $incident,
    ) {}
}
