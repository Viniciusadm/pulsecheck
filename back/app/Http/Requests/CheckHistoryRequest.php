<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CheckHistoryRequest extends FormRequest
{
    public const DEFAULT_HOURS = 24;

    public const MAX_DAYS = 90;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['from', 'to'])) {
                    return;
                }

                [$from, $to] = [$this->from(), $this->to()];

                if ($to->lessThanOrEqualTo($from)) {
                    $validator->errors()->add('to', __('The end of the period must be after its start.'));
                } elseif ($from->diffInDays($to) > self::MAX_DAYS) {
                    $validator->errors()->add('from', __('The period cannot be longer than :days days.', ['days' => self::MAX_DAYS]));
                }
            },
        ];
    }

    public function from(): CarbonImmutable
    {
        return $this->has('from')
            ? CarbonImmutable::parse($this->input('from'))
            : $this->to()->subHours(self::DEFAULT_HOURS);
    }

    public function to(): CarbonImmutable
    {
        return $this->has('to') ? CarbonImmutable::parse($this->input('to')) : CarbonImmutable::now();
    }
}
