<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkMonitorActionRequest extends FormRequest
{
    public const ACTIONS = ['pause', 'resume', 'delete'];

    public const MAX_MONITORS = 100;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:'.implode(',', self::ACTIONS)],
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_MONITORS],
            'ids.*' => ['integer', 'distinct', 'min:1'],
        ];
    }
}
