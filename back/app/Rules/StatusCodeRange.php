<?php

namespace App\Rules;

use App\Support\StatusCodeMatcher;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StatusCodeRange implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || StatusCodeMatcher::parseRange($value) === null) {
            $fail('Use a status code from 100 to 599 or a range such as 200-299.')->translate();
        }
    }
}
