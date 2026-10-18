<?php

namespace App\Http\Requests;

use App\Enums\NotificationChannelType;
use App\Models\NotificationChannel;

class UpdateNotificationChannelRequest extends StoreNotificationChannelRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'enabled' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            ...$this->configRules(),
        ];
    }

    protected function channelType(): NotificationChannelType
    {
        return $this->channel()->type;
    }

    protected function configRules(): array
    {
        if (! $this->has('config')) {
            return [];
        }

        $driver = $this->channelType()->driver();
        $secretKeys = $driver->secretKeys();

        $rules = collect($driver->rules())->map(function (array $rules, string $key) use ($secretKeys) {
            return in_array($key, $secretKeys, true)
                ? ['nullable', ...array_filter($rules, fn (mixed $rule) => $rule !== 'required' && $rule !== 'sometimes')]
                : $rules;
        })->all();

        return [
            'config' => ['array:'.implode(',', $this->configKeys($driver))],
            ...$this->prefixRules($rules),
        ];
    }

    private function channel(): NotificationChannel
    {
        return $this->route('notificationChannel');
    }
}
