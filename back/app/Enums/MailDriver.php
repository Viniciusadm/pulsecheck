<?php

namespace App\Enums;

use Illuminate\Validation\Rule;

enum MailDriver: string
{
    case Smtp = 'smtp';
    case Mailgun = 'mailgun';
    case Postmark = 'postmark';
    case Resend = 'resend';
    case Ses = 'ses';

    public const MAILGUN_ENDPOINTS = ['api.mailgun.net', 'api.eu.mailgun.net'];

    public const SMTP_SCHEMES = ['smtp', 'smtps'];

    public function rules(): array
    {
        return match ($this) {
            self::Smtp => [
                'host' => ['required', 'string', 'max:255'],
                'port' => ['required', 'integer', 'between:1,65535'],
                'scheme' => ['nullable', Rule::in(self::SMTP_SCHEMES)],
                'username' => ['nullable', 'string', 'max:255'],
                'password' => ['nullable', 'string', 'max:1000'],
            ],
            self::Mailgun => [
                'domain' => ['required', 'string', 'max:255'],
                'secret' => ['nullable', 'string', 'max:1000'],
                'endpoint' => ['required', Rule::in(self::MAILGUN_ENDPOINTS)],
            ],
            self::Postmark => [
                'token' => ['nullable', 'string', 'max:1000'],
                'message_stream_id' => ['nullable', 'string', 'max:255'],
            ],
            self::Resend => [
                'key' => ['nullable', 'string', 'max:1000'],
            ],
            self::Ses => [
                'key' => ['required', 'string', 'max:255'],
                'secret' => ['nullable', 'string', 'max:1000'],
                'region' => ['required', 'string', 'max:50', 'regex:/^[a-z]{2}(-[a-z]+)+-\d$/'],
            ],
        };
    }

    public function secretKeys(): array
    {
        return match ($this) {
            self::Smtp => ['password'],
            self::Mailgun, self::Ses => ['secret'],
            self::Postmark => ['token'],
            self::Resend => ['key'],
        };
    }

    public function requiredSecretKeys(): array
    {
        return $this === self::Smtp ? [] : $this->secretKeys();
    }

    public function toMailerConfig(array $config): array
    {
        $config = array_filter($config, fn (mixed $value) => $value !== null && $value !== '');

        return match ($this) {
            self::Smtp => ['transport' => 'smtp', 'timeout' => 10, ...$config, 'port' => (int) ($config['port'] ?? 587)],
            self::Mailgun => ['transport' => 'mailgun', ...$config],
            self::Postmark => ['transport' => 'postmark', ...$config],
            self::Resend => ['transport' => 'resend', ...$config],
            self::Ses => ['transport' => 'ses', ...$config],
        };
    }
}
