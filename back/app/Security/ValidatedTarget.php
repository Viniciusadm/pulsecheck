<?php

namespace App\Security;

final readonly class ValidatedTarget
{
    public function __construct(
        public string $url,
        public string $scheme,
        public string $host,
        public int $port,
        public array $ips,
        public bool $isIpLiteral,
    ) {}
}
