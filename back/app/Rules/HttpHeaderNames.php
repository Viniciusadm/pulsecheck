<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class HttpHeaderNames implements ValidationRule
{
    private const HEADER_NAME_PATTERN = '/^[A-Za-z0-9!#$%&\'*+.^_`|~-]{1,100}$/D';

    public function __construct(private array $reservedHeaders) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        foreach (array_keys($value) as $name) {
            if (! is_string($name) || ! preg_match(self::HEADER_NAME_PATTERN, $name)) {
                $fail('Header names may only contain letters, numbers and - _ . characters.')->translate();

                return;
            }

            if (in_array(strtolower($name), $this->reservedHeaders, true)) {
                $fail('The :name header is set by PulseCheck and cannot be overridden.')->translate(['name' => $name]);

                return;
            }
        }
    }
}
