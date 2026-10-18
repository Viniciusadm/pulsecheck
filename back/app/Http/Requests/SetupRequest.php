<?php

namespace App\Http\Requests;

use App\Support\SetupToken;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $setupToken = SetupToken::current();

        return [
            'instance_name' => ['required', 'string', 'max:100'],
            'timezone' => ['required', 'string', 'timezone:all'],
            'locale' => ['sometimes', 'string', Rule::in(config('pulsecheck.locales'))],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'token' => filled($setupToken)
                ? ['required', 'string', $this->matchesSetupToken($setupToken)]
                : ['exclude'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    private function matchesSetupToken(string $setupToken): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($setupToken): void {
            if (! is_string($value) || ! hash_equals($setupToken, $value)) {
                $fail('The setup token is invalid.')->translate();
            }
        };
    }
}
