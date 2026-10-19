<?php

namespace App\Security;

class SafeSocketConnector
{
    public function __construct(private UrlGuard $guard, private SocketOpener $opener) {}

    public function connect(string $host, int $port, float $timeout): void
    {
        $this->opener->probe($this->address('tcp', $this->guard->inspectEndpoint($host, $port)), $timeout);
    }

    public function certificate(string $host, int $port, float $timeout, bool $verify = true): ?array
    {
        $target = $this->guard->inspectEndpoint($host, $port);

        return $this->opener->probe($this->address('ssl', $target), $timeout, [
            'peer_name' => $target->host,
            'SNI_enabled' => true,
            'verify_peer' => $verify,
            'verify_peer_name' => $verify,
            'allow_self_signed' => ! $verify,
            'capture_peer_cert' => true,
        ]);
    }

    private function address(string $scheme, ValidatedTarget $target): string
    {
        $ip = $target->ips[0];

        return $scheme.'://'.(str_contains($ip, ':') ? "[{$ip}]" : $ip).':'.$target->port;
    }
}
