<?php

namespace App\Exceptions;

use RuntimeException;

class NotificationDeliveryException extends RuntimeException
{
    private const RETRYABLE_STATUSES = [408, 425, 429];

    private function __construct(string $message, public readonly bool $isRetryable)
    {
        parent::__construct($message);
    }

    public static function rejected(): self
    {
        return new self('The destination rejected the notification. Check the channel settings.', false);
    }

    public static function unavailable(): self
    {
        return new self('The destination could not be reached. Try again later.', true);
    }

    public static function forStatus(int $status): self
    {
        return $status >= 500 || in_array($status, self::RETRYABLE_STATUSES, true)
            ? self::unavailable()
            : self::rejected();
    }
}
