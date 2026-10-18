<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UptimeHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'days' => ['sometimes', 'integer', 'between:1,90'],
        ];
    }

    public function days(): int
    {
        return (int) $this->validated('days', 30);
    }
}
