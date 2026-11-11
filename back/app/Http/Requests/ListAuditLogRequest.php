<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListAuditLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['sometimes', 'integer', 'min:1'],
            'subject_type' => ['sometimes', 'string', 'max:64', 'regex:/^[a-z_]+$/'],
            'action' => ['sometimes', 'string', 'max:64', 'regex:/^[a-z_.]+$/'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
