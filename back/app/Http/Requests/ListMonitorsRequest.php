<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListMonitorsRequest extends FormRequest
{
    public const STATUS_FILTERS = ['up', 'degraded', 'down', 'pending', 'paused'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', 'in:'.implode(',', self::STATUS_FILTERS)],
        ];
    }
}
