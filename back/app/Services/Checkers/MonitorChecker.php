<?php

namespace App\Services\Checkers;

use App\Models\Monitor;
use App\Services\CheckOutcome;

interface MonitorChecker
{
    public function rules(): array;

    public function secretKeys(): array;

    public function defaultOptions(): array;

    public function check(Monitor $monitor): ?CheckOutcome;
}
