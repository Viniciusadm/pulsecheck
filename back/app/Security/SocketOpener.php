<?php

namespace App\Security;

use App\Security\Exceptions\SocketProbeException;

class SocketOpener
{
    private const ETIMEDOUT = 110;

    private const ECONNREFUSED = 111;

    public function probe(string $address, float $timeout, array $sslOptions = []): ?array
    {
        $context = stream_context_create($sslOptions === [] ? [] : ['ssl' => $sslOptions]);
        $warnings = [];

        set_error_handler(function (int $level, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        });

        try {
            $socket = stream_socket_client($address, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        } finally {
            restore_error_handler();
        }

        if ($socket === false) {
            throw $this->failure($errno, $errstr, $warnings, $sslOptions !== []);
        }

        $certificate = stream_context_get_params($socket)['options']['ssl']['peer_certificate'] ?? null;
        fclose($socket);

        return $certificate === null ? null : (openssl_x509_parse($certificate) ?: null);
    }

    private function failure(int $errno, string $errstr, array $warnings, bool $isTls): SocketProbeException
    {
        $message = trim($errstr) !== '' ? $errstr : (end($warnings) ?: 'Connection failed');

        return match (true) {
            $errno === self::ETIMEDOUT || stripos($message, 'timed out') !== false => new SocketProbeException(SocketProbeException::TIMEOUT, $message),
            $errno === self::ECONNREFUSED || stripos($message, 'refused') !== false => new SocketProbeException(SocketProbeException::REFUSED, $message),
            $isTls && $errno === 0 => new SocketProbeException(SocketProbeException::TLS, $this->tlsMessage($warnings, $message)),
            default => new SocketProbeException(SocketProbeException::OTHER, $message),
        };
    }

    private function tlsMessage(array $warnings, string $fallback): string
    {
        foreach ($warnings as $warning) {
            if (preg_match('/error:[0-9A-F]+:(?:[^:]*:){2}(.+)$/i', $warning, $matches)) {
                return trim($matches[1]);
            }
        }

        return $fallback;
    }
}
