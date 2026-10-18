<?php

namespace App\Services;

use App\Enums\CheckErrorType;
use App\Enums\WarningType;
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
        public bool $isDegraded = false,
        public ?WarningType $warningType = null,
        public ?string $warningMessage = null,
    ) {}

    public function withLatencyThreshold(?int $thresholdMs): self
    {
        if ($thresholdMs === null || ! $this->isSuccess || $this->isDegraded || $this->latencyMs === null || $this->latencyMs <= $thresholdMs) {
            return $this;
        }

        return new self(
            $this->startedAt,
            $this->finishedAt,
            $this->latencyMs,
            $this->statusCode,
            $this->isSuccess,
            $this->errorType,
            $this->errorMessage,
            true,
            WarningType::SlowResponse,
            __('Response time :actual ms exceeds :threshold ms.', ['actual' => $this->latencyMs, 'threshold' => $thresholdMs]),
        );
    }
}
