<?php

namespace App\Http\Requests;

use App\Enums\NotificationChannelType;
use App\Notifications\Channels\ChannelDriver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(NotificationChannelType::class)],
            'name' => ['required', 'string', 'max:100'],
            'enabled' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            ...$this->configRules(),
        ];
    }

    protected function channelType(): ?NotificationChannelType
    {
        return NotificationChannelType::tryFrom((string) $this->input('type'));
    }

    protected function configRules(): array
    {
        $driver = $this->channelType()?->driver();

        if ($driver === null) {
            return ['config' => ['required', 'array']];
        }

        return [
            'config' => ['required', 'array:'.implode(',', $this->configKeys($driver))],
            ...$this->prefixRules($driver->rules()),
        ];
    }

    protected function configKeys(ChannelDriver $driver): array
    {
        return array_values(array_unique(array_map(
            fn (string $key) => explode('.', $key)[0],
            array_keys($driver->rules()),
        )));
    }

    protected function prefixRules(array $rules): array
    {
        return collect($rules)->mapWithKeys(fn (array $rule, string $key) => ["config.{$key}" => $rule])->all();
    }
}
