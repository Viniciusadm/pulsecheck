<?php

namespace App\Services;

use App\Enums\CheckErrorType;
use Carbon\CarbonImmutable;

final readonly class CheckOutcome
{
    public function __construct(
        public CarbonImmutable $startedAt,
        public CarbonImmutable $finishedAt,
        public ?int $latencyMs,
        public ?int $statusCode,
        public bool $isSuccess,
        public ?CheckErrorType $errorType,
        public ?string $errorMessage,
    ) {}
}
