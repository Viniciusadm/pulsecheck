<?php

namespace App\Http\Requests;

use App\Rules\SafeUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMonitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'string', new SafeUrl],
            'interval_minutes' => ['sometimes', 'integer', Rule::in(config('pulsecheck.allowed_intervals'))],
            'timeout_seconds' => ['sometimes', 'integer', 'between:'.config('pulsecheck.timeout_min').','.config('pulsecheck.timeout_max')],
            'notification_channel_ids' => ['sometimes', 'array'],
            'notification_channel_ids.*' => ['integer', 'distinct', Rule::exists('notification_channels', 'id')],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['interval_minutes', 'timeout_seconds'])) {
                    return;
                }

                $intervalMinutes = (int) $this->input('interval_minutes', $this->currentIntervalMinutes());
                $timeoutSeconds = (int) $this->input('timeout_seconds', $this->currentTimeoutSeconds());

                if ($timeoutSeconds >= $intervalMinutes * 60) {
                    $validator->errors()->add('timeout_seconds', __('The timeout must be shorter than the check interval.'));
                }
            },
        ];
    }

    protected function currentIntervalMinutes(): int
    {
        return (int) config('pulsecheck.default_interval');
    }

    protected function currentTimeoutSeconds(): int
    {
        return (int) config('pulsecheck.timeout_default');
    }
}
