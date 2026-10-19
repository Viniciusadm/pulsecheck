<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rules\Unique;

class UpdateStatusPageRequest extends StoreStatusPageRequest
{
    public function rules(): array
    {
        return collect(parent::rules())
            ->map(fn (array $rules, string $key) => str_contains($key, '.')
                ? $rules
                : ['sometimes', ...array_filter($rules, fn (mixed $rule) => $rule !== 'sometimes' && $rule !== 'required')])
            ->all();
    }

    protected function uniqueSlug(): Unique
    {
        return parent::uniqueSlug()->ignore($this->route('statusPage'));
    }
}
