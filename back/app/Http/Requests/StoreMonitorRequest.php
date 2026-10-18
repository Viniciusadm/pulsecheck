<?php

namespace App\Http\Requests;

use App\Enums\MonitorType;
use App\Http\Requests\Concerns\ValidatesTeamAssignment;
use App\Models\NotificationChannel;
use App\Services\Checkers\HttpChecker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMonitorRequest extends FormRequest
{
    use ValidatesTeamAssignment;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['sometimes', Rule::enum(MonitorType::class)],
            'interval_minutes' => ['sometimes', 'integer', Rule::in(config('pulsecheck.allowed_intervals'))],
            'timeout_seconds' => ['sometimes', 'integer', 'between:'.config('pulsecheck.timeout_min').','.config('pulsecheck.timeout_max')],
            'latency_threshold_ms' => ['sometimes', 'nullable', 'integer', 'between:1,60000'],
            'notification_channel_ids' => ['sometimes', 'array'],
            'notification_channel_ids.*' => ['integer', 'distinct', Rule::in(NotificationChannel::query()->visibleTo($this->user())->pluck('id')->all())],
            ...$this->checkRules(),
            ...$this->teamRules(),
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['interval_minutes', 'timeout_seconds', 'latency_threshold_ms'])) {
                    return;
                }

                $intervalMinutes = (int) $this->input('interval_minutes', $this->currentIntervalMinutes());
                $timeoutSeconds = (int) $this->input('timeout_seconds', $this->currentTimeoutSeconds());

                if ($timeoutSeconds >= $intervalMinutes * 60) {
                    $validator->errors()->add('timeout_seconds', __('The timeout must be shorter than the check interval.'));
                }

                $latencyThresholdMs = $this->input('latency_threshold_ms', $this->currentLatencyThresholdMs());

                if ($latencyThresholdMs !== null && (int) $latencyThresholdMs >= $timeoutSeconds * 1000) {
                    $validator->errors()->add('latency_threshold_ms', __('The response time limit must be shorter than the timeout.'));
                }
            },
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $method = $this->input('options.method', $this->currentOption('method'));

                if ($this->filled('secrets.body') && in_array($method, HttpChecker::METHODS_WITHOUT_BODY, true)) {
                    $validator->errors()->add('secrets.body', __('GET and HEAD requests cannot have a body.'));
                }

                $authType = ($this->input('secrets.auth') ?? $this->currentSecret('auth'))['type'] ?? 'none';
                $headerNames = array_map('strtolower', array_keys((array) ($this->input('secrets.headers') ?? $this->currentSecret('headers'))));

                if ($authType !== 'none' && in_array('authorization', $headerNames, true)) {
                    $validator->errors()->add('secrets.headers', __('Remove the Authorization header or the authentication: only one can be used.'));
                }
            },
        ];
    }

    protected function monitorType(): MonitorType
    {
        return MonitorType::tryFrom((string) $this->input('type', MonitorType::Http->value)) ?? MonitorType::Http;
    }

    protected function checkRules(): array
    {
        return $this->monitorType()->checker()->rules();
    }

    protected function currentIntervalMinutes(): int
    {
        return (int) config('pulsecheck.default_interval');
    }

    protected function currentTimeoutSeconds(): int
    {
        return (int) config('pulsecheck.timeout_default');
    }

    protected function currentLatencyThresholdMs(): ?int
    {
        return null;
    }

    protected function currentOption(string $key): mixed
    {
        return $this->monitorType()->checker()->defaultOptions()[$key] ?? null;
    }

    protected function currentSecret(string $key): mixed
    {
        return null;
    }
}
