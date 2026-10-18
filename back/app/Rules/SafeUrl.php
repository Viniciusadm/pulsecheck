<?php

namespace App\Rules;

use App\Security\Exceptions\UnresolvableHostException;
use App\Security\Exceptions\UnsafeUrlException;
use App\Security\UrlGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a valid URL.')->translate();

            return;
        }

        try {
            app(UrlGuard::class)->inspect($value);
        } catch (UnsafeUrlException $exception) {
            $fail($exception->reason->message())->translate();
        } catch (UnresolvableHostException) {
            $fail('The :attribute host could not be resolved.')->translate();
        }
    }
}
