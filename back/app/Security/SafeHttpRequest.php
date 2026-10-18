<?php

namespace App\Security;

final readonly class SafeHttpRequest
{
    public function __construct(
        public string $url,
        public string $method = 'GET',
        public array $headers = [],
        public ?string $body = null,
        public bool $followRedirects = true,
        public float $timeout = 10,
        public float $connectTimeout = 5,
        public int $maxBodyBytes = 0,
    ) {}
}
