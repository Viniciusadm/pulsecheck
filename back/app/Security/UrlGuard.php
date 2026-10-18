<?php

namespace App\Security;

use App\Security\Exceptions\UnresolvableHostException;
use App\Security\Exceptions\UnsafeUrlException;
use Symfony\Component\HttpFoundation\IpUtils;

class UrlGuard
{
    private const ALWAYS_FORBIDDEN_RANGES = [
        '0.0.0.0/8',
        '169.254.0.0/16',
        '100.100.100.200/32',
        '192.0.0.192/32',
        '224.0.0.0/4',
        '240.0.0.0/4',
        '::/96',
        '::ffff:0:0/96',
        '::ffff:0:0:0/96',
        '64:ff9b::/96',
        '64:ff9b:1::/48',
        '2001::/32',
        '2002::/16',
        'fd00:ec2::254/128',
        'ff00::/8',
    ];

    private const PRIVATE_RANGES = [
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.168.0.0/16',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '100::/64',
        '2001::/23',
        '2001:db8::/32',
        'fc00::/7',
        'fe80::/10',
        'fec0::/10',
    ];

    private const DEFAULT_PORTS = ['http' => 80, 'https' => 443];

    public function __construct(private DnsResolver $resolver) {}

    public function inspect(string $url): ValidatedTarget
    {
        $target = $this->validateSyntax($url);

        $ips = $target->isIpLiteral ? $target->ips : $this->resolveAndValidate($target->host);

        return new ValidatedTarget($target->url, $target->scheme, $target->host, $target->port, $ips, $target->isIpLiteral);
    }

    public function validateSyntax(string $url): ValidatedTarget
    {
        if (strlen($url) > config('pulsecheck.max_url_length')) {
            throw new UnsafeUrlException(UnsafeUrlReason::TooLong);
        }

        if (preg_match('/[\s\x00-\x1f\x7f\\\\]/', $url)) {
            throw new UnsafeUrlException(UnsafeUrlReason::Invalid);
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'])) {
            throw new UnsafeUrlException(UnsafeUrlReason::Invalid);
        }

        $scheme = strtolower($parts['scheme']);

        if (! array_key_exists($scheme, self::DEFAULT_PORTS)) {
            throw new UnsafeUrlException(UnsafeUrlReason::UnsupportedScheme);
        }

        if (! isset($parts['host']) || $parts['host'] === '') {
            throw new UnsafeUrlException(UnsafeUrlReason::Invalid);
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new UnsafeUrlException(UnsafeUrlReason::Credentials);
        }

        $port = $parts['port'] ?? self::DEFAULT_PORTS[$scheme];

        if ($port < 1 || $port > 65535) {
            throw new UnsafeUrlException(UnsafeUrlReason::Invalid);
        }

        [$host, $isIpLiteral] = $this->normalizeHost($parts['host']);

        if ($isIpLiteral && $this->isForbiddenIp($host)) {
            throw new UnsafeUrlException(UnsafeUrlReason::ForbiddenDestination);
        }

        if (! $isIpLiteral && $this->isForbiddenHostname($host)) {
            throw new UnsafeUrlException(UnsafeUrlReason::ForbiddenDestination);
        }

        $authority = str_contains($host, ':') ? "[{$host}]" : $host;

        if (isset($parts['port']) && $parts['port'] !== self::DEFAULT_PORTS[$scheme]) {
            $authority .= ':'.$parts['port'];
        }

        $normalizedUrl = $scheme.'://'.$authority
            .($parts['path'] ?? '/')
            .(isset($parts['query']) ? '?'.$parts['query'] : '');

        return new ValidatedTarget($normalizedUrl, $scheme, $host, $port, $isIpLiteral ? [$host] : [], $isIpLiteral);
    }

    public function resolveAndValidate(string $host): array
    {
        $ips = $this->resolver->resolve($host);

        if ($ips === []) {
            throw new UnresolvableHostException;
        }

        foreach ($ips as $ip) {
            if ($this->isForbiddenIp($ip)) {
                throw new UnsafeUrlException(UnsafeUrlReason::ForbiddenDestination);
            }
        }

        return $ips;
    }

    public function isForbiddenIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return true;
        }

        if (IpUtils::checkIp($ip, self::ALWAYS_FORBIDDEN_RANGES)) {
            return true;
        }

        if ($this->allowsPrivateTargets()) {
            return false;
        }

        return IpUtils::checkIp($ip, self::PRIVATE_RANGES);
    }

    private function normalizeHost(string $host): array
    {
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $ip = substr($host, 1, -1);

            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
                throw new UnsafeUrlException(UnsafeUrlReason::Invalid);
            }

            return [strtolower($ip), true];
        }

        $host = rtrim(strtolower($host), '.');

        if (preg_match('/[^\x20-\x7e]/', $host)) {
            $host = idn_to_ascii($host, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);

            if ($host === false) {
                throw new UnsafeUrlException(UnsafeUrlReason::Invalid);
            }
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return [$host, true];
        }

        $labels = explode('.', $host);

        if (preg_match('/^(0x[0-9a-f]*|[0-9]+)$/', end($labels))) {
            throw new UnsafeUrlException(UnsafeUrlReason::ForbiddenDestination);
        }

        if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            throw new UnsafeUrlException(UnsafeUrlReason::Invalid);
        }

        return [$host, false];
    }

    private function isForbiddenHostname(string $host): bool
    {
        if (in_array($host, config('pulsecheck.blocked_hostnames'), true)) {
            return true;
        }

        if ($this->allowsPrivateTargets()) {
            return false;
        }

        return ! str_contains($host, '.')
            || $host === 'localhost'
            || str_ends_with($host, '.localhost');
    }

    private function allowsPrivateTargets(): bool
    {
        return (bool) config('pulsecheck.allow_private_targets');
    }
}
