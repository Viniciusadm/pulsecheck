<?php

namespace App\Security;

final readonly class SafeHttpResponse
{
    public function __construct(
        public int $statusCode,
        public string $finalUrl,
        public int $redirects,
        public ?string $body = null,
        public bool $bodyTruncated = false,
    ) {}
}
