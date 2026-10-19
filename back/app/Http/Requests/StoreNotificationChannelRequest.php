<?php

namespace App\Http\Requests;

use App\Enums\MessageFormat;
use App\Enums\NotificationChannelType;
use App\Enums\NotificationType;
use App\Http\Requests\Concerns\ValidatesTeamAssignment;
use App\Notifications\Channels\ChannelDriver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationChannelRequest extends FormRequest
{
    use ValidatesTeamAssignment;

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
            ...$this->deliveryRules(),
            ...$this->configRules(),
            ...$this->teamRules(),
        ];
    }

    protected function deliveryRules(): array
    {
        return [
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['distinct', Rule::enum(NotificationType::class)],
            'quiet_hours_start' => ['nullable', 'date_format:H:i', 'required_with:quiet_hours_end', 'different:quiet_hours_end'],
            'quiet_hours_end' => ['nullable', 'date_format:H:i', 'required_with:quiet_hours_start'],
            'quiet_hours_allow_critical' => ['sometimes', 'boolean'],
            'message_format' => ['sometimes', Rule::enum(MessageFormat::class)],
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
