<?php

namespace App\Support;

final readonly class StatusCodeMatcher
{
    public const DEFAULT_RANGES = ['200-399'];

    private function __construct(private array $ranges) {}

    public static function fromList(?array $codes): self
    {
        $ranges = array_values(array_filter(array_map(self::parseRange(...), $codes ?: self::DEFAULT_RANGES)));

        return new self($ranges);
    }

    public static function parseRange(string $value): ?array
    {
        if (! preg_match('/^(\d{3})(?:-(\d{3}))?$/D', trim($value), $matches)) {
            return null;
        }

        $low = (int) $matches[1];
        $high = isset($matches[2]) ? (int) $matches[2] : $low;

        if ($low < 100 || $high > 599 || $low > $high) {
            return null;
        }

        return [$low, $high];
    }

    public function matches(int $statusCode): bool
    {
        foreach ($this->ranges as [$low, $high]) {
            if ($statusCode >= $low && $statusCode <= $high) {
                return true;
            }
        }

        return false;
    }
}
