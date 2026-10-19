<?php

namespace App\Http\Requests;

use App\Enums\MonitorType;
use App\Models\Monitor;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateMonitorRequest extends StoreMonitorRequest
{
    public function rules(): array
    {
        return [
            ...collect(parent::rules())
                ->map(fn (array $rules, string $key) => str_contains($key, '.')
                    ? ['exclude_without:'.Str::before($key, '.'), ...$rules]
                    : ['sometimes', ...array_filter($rules, fn (mixed $rule) => $rule !== 'sometimes')])
                ->all(),
            'type' => ['prohibited'],
            'clear_secrets' => ['sometimes', 'array'],
            'clear_secrets.*' => ['string', 'distinct', Rule::in($this->monitorType()->checker()->secretKeys())],
        ];
    }

    protected function monitorType(): MonitorType
    {
        return $this->monitor()->type;
    }

    protected function checkRules(): array
    {
        $checker = $this->monitorType()->checker();
        $secretKeys = array_map(fn (string $key) => "secrets.{$key}", $checker->secretKeys());

        return collect($checker->rules())
            ->map(fn (array $rules, string $key) => in_array($key, $secretKeys, true)
                ? ['nullable', ...array_filter($rules, fn (mixed $rule) => ! in_array($rule, ['required', 'sometimes'], true))]
                : $rules)
            ->all();
    }

    protected function currentIntervalMinutes(): int
    {
        return $this->monitor()->interval_minutes;
    }

    protected function currentTimeoutSeconds(): int
    {
        return $this->monitor()->timeout_seconds;
    }

    protected function currentLatencyThresholdMs(): ?int
    {
        return $this->monitor()->latency_threshold_ms;
    }

    protected function currentOption(string $key): mixed
    {
        return $this->monitor()->option($key);
    }

    protected function currentSecret(string $key): mixed
    {
        return $this->monitor()->secret($key);
    }

    private function monitor(): Monitor
    {
        return $this->route('monitor');
    }
}
