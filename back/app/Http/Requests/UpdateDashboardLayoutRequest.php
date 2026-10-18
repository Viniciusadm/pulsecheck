<?php

namespace App\Http\Requests;

use App\Enums\DashboardWidgetType;
use App\Enums\StatsPeriod;
use App\Models\Monitor;
use App\Support\DashboardLayout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateDashboardLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => ['required', Rule::enum(StatsPeriod::class)],
            'widgets' => ['sometimes', 'array', 'max:'.DashboardLayout::MAX_WIDGETS],
            'widgets.*' => ['array:id,type,x,y,w,h,options'],
            'widgets.*.id' => ['required', 'string', 'max:64', 'distinct'],
            'widgets.*.type' => ['required', Rule::enum(DashboardWidgetType::class)],
            'widgets.*.x' => ['required', 'integer', 'min:0', 'max:'.(DashboardLayout::COLUMNS - 1)],
            'widgets.*.y' => ['required', 'integer', 'min:0', 'max:1000'],
            'widgets.*.w' => ['required', 'integer', 'min:1', 'max:'.DashboardLayout::COLUMNS],
            'widgets.*.h' => ['required', 'integer', 'min:1', 'max:'.DashboardLayout::MAX_HEIGHT],
            'widgets.*.options' => ['sometimes', 'array:monitor_id'],
            'widgets.*.options.monitor_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $visibleMonitorIds = null;

                foreach ($this->input('widgets', []) as $index => $widget) {
                    $type = DashboardWidgetType::from($widget['type']);

                    if ($widget['x'] + $widget['w'] > DashboardLayout::COLUMNS) {
                        $validator->errors()->add("widgets.{$index}.w", __('The widget does not fit in the grid.'));
                    }

                    if ($widget['w'] < $type->minWidth() || $widget['h'] < $type->minHeight()) {
                        $validator->errors()->add("widgets.{$index}.w", __('The widget is smaller than its minimum size.'));
                    }

                    if (! $type->requiresMonitor()) {
                        continue;
                    }

                    $visibleMonitorIds ??= Monitor::query()->visibleTo($this->user())->pluck('id')->all();

                    if (! in_array($widget['options']['monitor_id'] ?? null, $visibleMonitorIds, true)) {
                        $validator->errors()->add("widgets.{$index}.options.monitor_id", __('The selected monitor is invalid.'));
                    }
                }
            },
        ];
    }

    public function period(): StatsPeriod
    {
        return StatsPeriod::from($this->validated('period'));
    }

    public function widgets(): array
    {
        return collect($this->validated('widgets'))
            ->map(function (array $widget) {
                $type = DashboardWidgetType::from($widget['type']);

                return [
                    'id' => $widget['id'],
                    'type' => $type->value,
                    'x' => (int) $widget['x'],
                    'y' => (int) $widget['y'],
                    'w' => (int) $widget['w'],
                    'h' => (int) $widget['h'],
                    'options' => $type->requiresMonitor() ? ['monitor_id' => (int) $widget['options']['monitor_id']] : [],
                ];
            })
            ->all();
    }
}
