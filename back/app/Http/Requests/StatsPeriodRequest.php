<?php

namespace App\Http\Requests;

use App\Enums\StatsPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StatsPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => ['sometimes', Rule::enum(StatsPeriod::class)],
        ];
    }

    public function period(): StatsPeriod
    {
        return StatsPeriod::from($this->validated('period', StatsPeriod::Day->value));
    }
}
