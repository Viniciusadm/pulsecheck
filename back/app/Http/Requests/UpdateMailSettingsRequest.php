<?php

namespace App\Http\Requests;

use App\Enums\MailDriver;
use App\Models\MailSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class UpdateMailSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'driver' => ['required', Rule::enum(MailDriver::class)],
            'from_address' => ['required', 'string', 'max:255', 'email:rfc'],
            'from_name' => ['nullable', 'string', 'max:100'],
            'config' => ['present', 'array'],
            'clear_secrets' => ['sometimes', 'array'],
            'clear_secrets.*' => ['string', 'distinct', Rule::in($this->clearableSecretKeys())],
            ...$this->configRules(),
        ];
    }

    public function attributes(): array
    {
        return collect($this->driver()?->rules() ?? [])
            ->keys()
            ->mapWithKeys(fn (string $key) => ["config.{$key}" => str_replace('_', ' ', $key)])
            ->all();
    }

    public function driver(): ?MailDriver
    {
        return MailDriver::tryFrom((string) $this->input('driver'));
    }

    public function config(): array
    {
        return Arr::only($this->validated('config'), array_keys($this->driver()->rules()));
    }

    public function clearableSecretKeys(): array
    {
        $driver = $this->driver();

        return $driver === null ? [] : array_values(array_diff($driver->secretKeys(), $driver->requiredSecretKeys()));
    }

    private function configRules(): array
    {
        $driver = $this->driver();

        if ($driver === null) {
            return [];
        }

        $stored = MailSettings::current();
        $storedSecrets = $stored?->driver === $driver ? array_keys(array_filter($stored->config ?? [])) : [];

        return collect($driver->rules())
            ->mapWithKeys(function (array $rules, string $key) use ($driver, $storedSecrets) {
                if (in_array($key, $driver->requiredSecretKeys(), true) && ! in_array($key, $storedSecrets, true)) {
                    $rules = ['required', ...array_diff($rules, ['nullable'])];
                }

                return ["config.{$key}" => $rules];
            })
            ->all();
    }
}
