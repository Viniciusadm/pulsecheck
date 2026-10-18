<?php

namespace App\Http\Requests;

use App\Models\Monitor;

class UpdateMonitorRequest extends StoreMonitorRequest
{
    public function rules(): array
    {
        return array_map(
            fn (array $rules) => ['sometimes', ...array_filter($rules, fn (mixed $rule) => $rule !== 'sometimes')],
            parent::rules(),
        );
    }

    protected function currentIntervalMinutes(): int
    {
        return $this->monitor()->interval_minutes;
    }

    protected function currentTimeoutSeconds(): int
    {
        return $this->monitor()->timeout_seconds;
    }

    private function monitor(): Monitor
    {
        return $this->route('monitor');
    }
}
