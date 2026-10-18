<?php

namespace App\Exceptions;

use RuntimeException;

class TelegramDeliveryException extends RuntimeException
{
    private function __construct(string $message, public readonly bool $isRetryable)
    {
        parent::__construct($message);
    }

    public static function rejected(): self
    {
        return new self('Telegram rejected the token or chat ID.', false);
    }

    public static function unavailable(): self
    {
        return new self('Telegram could not be reached. Try again later.', true);
    }
}
