<?php

namespace App\Notifications\Channels;

use App\Models\NotificationChannel;
use App\Notifications\AlertMessage;
use App\Rules\SafeUrl;
use Closure;

class WebhookDriver extends HttpChannelDriver
{
    public const MAX_HEADERS = 10;

    private const HEADER_NAME_PATTERN = '/^[A-Za-z0-9!#$%&\'*+.^_`|~-]{1,100}$/D';

    private const RESERVED_HEADERS = ['host', 'content-length', 'content-type', 'transfer-encoding', 'connection', 'user-agent'];

    public function rules(): array
    {
        return [
            'url' => ['required', 'string', new SafeUrl],
            'headers' => ['sometimes', 'array', 'max:'.self::MAX_HEADERS, $this->validateHeaderNames(...)],
            'headers.*' => ['required', 'string', 'max:1000', 'regex:/^[^\r\n\x00]*$/D'],
        ];
    }

    public function secretKeys(): array
    {
        return ['headers'];
    }

    public function send(NotificationChannel $channel, AlertMessage $message): void
    {
        $this->post($channel->setting('url'), $message->toPayload(), $channel->setting('headers') ?? []);
    }

    private function validateHeaderNames(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        foreach (array_keys($value) as $name) {
            if (! is_string($name) || ! preg_match(self::HEADER_NAME_PATTERN, $name)) {
                $fail('Header names may only contain letters, numbers and - _ . characters.')->translate();

                return;
            }

            if (in_array(strtolower($name), self::RESERVED_HEADERS, true)) {
                $fail('The :name header is set by PulseCheck and cannot be overridden.')->translate(['name' => $name]);

                return;
            }
        }
    }
}
