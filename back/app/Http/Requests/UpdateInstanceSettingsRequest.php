<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInstanceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'timezone' => ['required', 'string', 'timezone:all'],
            'locale' => ['sometimes', 'string', Rule::in(config('pulsecheck.locales'))],
        ];
    }
}
