<?php

namespace App\Http\Requests;

use App\Enums\MaintenanceWindowType;
use App\Models\Monitor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMaintenanceWindowRequest extends FormRequest
{
    private const MAX_ONE_OFF_DAYS = 31;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(MaintenanceWindowType::class)],
            'starts_at' => ['exclude_unless:type,one_off', 'required', 'date'],
            'ends_at' => ['exclude_unless:type,one_off', 'required', 'date', 'after:starts_at'],
            'weekdays' => ['exclude_unless:type,recurring', 'required', 'array', 'min:1', 'max:7'],
            'weekdays.*' => ['exclude_unless:type,recurring', 'integer', 'distinct', 'between:1,7'],
            'start_time' => ['exclude_unless:type,recurring', 'required', 'date_format:H:i'],
            'end_time' => ['exclude_unless:type,recurring', 'required', 'date_format:H:i', 'different:start_time'],
            'timezone' => ['exclude_unless:type,recurring', 'required', 'timezone:all'],
            'monitor_ids' => ['sometimes', 'array'],
            'monitor_ids.*' => ['integer', 'distinct', Rule::in(Monitor::query()->visibleTo($this->user())->pluck('id')->all())],
            'team_ids' => ['sometimes', 'array'],
            'team_ids.*' => ['integer', 'distinct', Rule::in($this->user()->visibleTeamIds())],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->input('type') === MaintenanceWindowType::OneOff->value
                    && CarbonImmutable::parse($this->input('starts_at'))->addDays(self::MAX_ONE_OFF_DAYS)->lessThan(CarbonImmutable::parse($this->input('ends_at')))) {
                    $validator->errors()->add('ends_at', __('A maintenance window can last at most :days days.', ['days' => self::MAX_ONE_OFF_DAYS]));
                }

                if ($this->targetCount() === 0) {
                    $validator->errors()->add('monitor_ids', __('Choose at least one monitor or team.'));
                }
            },
        ];
    }

    protected function targetCount(): int
    {
        return count($this->input('monitor_ids', [])) + count($this->input('team_ids', []));
    }
}
