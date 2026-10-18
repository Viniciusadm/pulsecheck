<?php

namespace Tests\Feature\Security;

use App\Enums\CheckErrorType;
use App\Security\Exceptions\UnresolvableHostException;
use App\Security\Exceptions\UnsafeUrlException;
use App\Security\UrlGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\FakesDns;
use Tests\TestCase;

class UrlGuardTest extends TestCase
{
    use FakesDns;

    private const PUBLIC_IPV4 = '93.184.215.14';

    private const PUBLIC_IPV6 = '2606:2800:21f:cb07:6820:80da:af6b:8b2c';

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeDns([
            'example.com' => [self::PUBLIC_IPV4, self::PUBLIC_IPV6],
            'localhost' => ['127.0.0.1'],
            'metadata.google.internal' => [self::PUBLIC_IPV4],
            'xn--bcher-kva.example' => [self::PUBLIC_IPV4],
            'internal.example.com' => ['10.0.0.5'],
            'mixed.example.com' => [self::PUBLIC_IPV4, '192.168.0.10'],
            'rebind.example.com' => ['::ffff:169.254.169.254'],
        ]);
    }

    private function guard(): UrlGuard
    {
        return $this->app->make(UrlGuard::class);
    }

    public static function unsafeUrls(): array
    {
        return [
            'loopback ip' => ['http://127.0.0.1'],
            'localhost' => ['http://localhost'],
            'localhost subdomain' => ['http://api.localhost'],
            'ipv6 loopback' => ['http://[::1]'],
            'cloud metadata ip' => ['http://169.254.169.254/latest/meta-data'],
            'cloud metadata hostname' => ['http://metadata.google.internal/computeMetadata/v1/'],
            'private 10/8' => ['http://10.0.0.1'],
            'private 192.168/16' => ['http://192.168.1.1'],
            'private 172.16/12' => ['http://172.16.5.4'],
            'decimal ip' => ['http://2130706433'],
            'hex ip' => ['http://0x7f.1'],
            'octal ip' => ['http://0177.0.0.1'],
            'short ip' => ['http://127.1'],
            'zero padded ip' => ['http://127.000.000.001'],
            'ipv4 mapped ipv6' => ['http://[::ffff:127.0.0.1]'],
            'ipv4 mapped ipv6 hex' => ['http://[::ffff:7f00:1]'],
            'ipv4 translated ipv6' => ['http://[::ffff:0:7f00:1]'],
            'unspecified' => ['http://0.0.0.0'],
            'ftp' => ['ftp://example.com/file'],
            'file' => ['file:///etc/passwd'],
            'gopher' => ['gopher://example.com:70/_'],
            'javascript' => ['javascript:alert(1)'],
            'credentials' => ['http://user:pass@example.com'],
            'username only' => ['http://user@example.com'],
            'no host' => ['http:///path'],
            'not a url' => ['example.com'],
            'single label host' => ['http://intranet/'],
            'invalid hostname characters' => ['http://exa_mple.com'],
            'host resolving to private ip' => ['http://internal.example.com'],
            'host with one private ip among many' => ['http://mixed.example.com'],
            'host resolving to mapped metadata ip' => ['http://rebind.example.com'],
        ];
    }

    #[DataProvider('unsafeUrls')]
    public function test_rejects_unsafe_urls(string $url): void
    {
        $this->expectException(UnsafeUrlException::class);

        $this->guard()->inspect($url);
    }

    public function test_rejects_urls_longer_than_the_limit(): void
    {
        $this->expectException(UnsafeUrlException::class);

        $this->guard()->inspect('https://example.com/'.str_repeat('a', 2048));
    }

    public function test_accepts_a_public_url_and_returns_its_validated_target(): void
    {
        $target = $this->guard()->inspect('https://example.com/health?full=1');

        $this->assertSame('https', $target->scheme);
        $this->assertSame('example.com', $target->host);
        $this->assertSame(443, $target->port);
        $this->assertSame([self::PUBLIC_IPV4, self::PUBLIC_IPV6], $target->ips);
    }

    public function test_normalizes_case_trailing_dot_and_default_port(): void
    {
        $target = $this->guard()->inspect('HTTP://EXAMPLE.com./');

        $this->assertSame('http', $target->scheme);
        $this->assertSame('example.com', $target->host);
        $this->assertSame(80, $target->port);
    }

    public function test_accepts_any_explicit_port(): void
    {
        $this->assertSame(8443, $this->guard()->inspect('https://example.com:8443/')->port);
    }

    public function test_converts_international_domain_names_to_ascii(): void
    {
        $this->assertSame('xn--bcher-kva.example', $this->guard()->inspect('https://bücher.example/')->host);
    }

    public function test_accepts_public_ip_literals_without_dns_lookup(): void
    {
        $this->assertSame(['1.1.1.1'], $this->guard()->inspect('http://1.1.1.1/')->ips);
        $this->assertSame(['2606:4700:4700::1111'], $this->guard()->inspect('http://[2606:4700:4700::1111]/')->ips);
        $this->assertSame([], $this->dnsLookups->getArrayCopy());
    }

    public function test_unresolvable_host_is_reported_as_dns_error(): void
    {
        try {
            $this->guard()->inspect('https://does-not-exist.example.com');
            $this->fail('Expected an unresolvable host exception.');
        } catch (UnresolvableHostException $exception) {
            $this->assertSame(CheckErrorType::Dns, $exception->errorType());
        }
    }

    public function test_forbidden_destination_is_reported_as_blocked(): void
    {
        try {
            $this->guard()->inspect('http://10.0.0.1');
            $this->fail('Expected an unsafe URL exception.');
        } catch (UnsafeUrlException $exception) {
            $this->assertSame(CheckErrorType::BlockedDestination, $exception->errorType());
        }
    }

    public static function forbiddenIps(): array
    {
        return array_map(fn (string $ip) => [$ip], [
            '0.0.0.0', '0.255.255.255', '10.0.0.1', '10.255.255.255', '100.64.0.1', '100.127.255.255',
            '127.0.0.1', '127.255.255.254', '169.254.0.1', '169.254.169.254', '172.16.0.1', '172.31.255.255',
            '192.0.0.1', '192.0.2.1', '192.168.0.1', '198.18.0.1', '198.19.255.255', '198.51.100.1',
            '203.0.113.1', '224.0.0.1', '239.255.255.255', '240.0.0.1', '255.255.255.255',
            '::', '::1', '::ffff:127.0.0.1', '::ffff:8.8.8.8', '::127.0.0.1', '64:ff9b::7f00:1', '64:ff9b:1::1',
            '100::1', '2001::1', '2001:db8::1', '2002:7f00:1::1', 'fc00::1', 'fd12:3456::1', 'fe80::1',
            'fec0::1', 'ff02::1', 'not-an-ip', '',
        ]);
    }

    #[DataProvider('forbiddenIps')]
    public function test_forbidden_ip_ranges(string $ip): void
    {
        $this->assertTrue($this->guard()->isForbiddenIp($ip));
    }

    public static function publicIps(): array
    {
        return array_map(fn (string $ip) => [$ip], [
            '1.1.1.1', '8.8.8.8', '93.184.215.14', '9.255.255.255', '11.0.0.0', '100.63.255.255', '100.128.0.0',
            '172.15.255.255', '172.32.0.0', '192.167.255.255', '192.169.0.0', '198.17.255.255', '198.20.0.0',
            '223.255.255.255', '2606:4700:4700::1111', '2a00:1450:4001:80b::200e',
        ]);
    }

    #[DataProvider('publicIps')]
    public function test_public_ips_are_allowed(string $ip): void
    {
        $this->assertFalse($this->guard()->isForbiddenIp($ip));
    }

    public function test_private_targets_can_be_allowed_explicitly(): void
    {
        config(['pulsecheck.allow_private_targets' => true]);

        $this->assertSame(['10.0.0.5'], $this->guard()->inspect('http://internal.example.com')->ips);
        $this->assertSame(['192.168.1.1'], $this->guard()->inspect('http://192.168.1.1')->ips);
        $this->assertSame(['127.0.0.1'], $this->guard()->inspect('http://localhost:8080')->ips);
    }

    public static function alwaysForbiddenUrls(): array
    {
        return [
            'metadata ip' => ['http://169.254.169.254/latest/meta-data'],
            'aws ipv6 metadata ip' => ['http://[fd00:ec2::254]/'],
            'metadata hostname' => ['http://metadata.google.internal/'],
            'aws ecs task metadata' => ['http://169.254.170.2/v2/credentials'],
            'any link-local address' => ['http://169.254.10.10/'],
            'alibaba metadata' => ['http://100.100.100.200/latest/meta-data'],
            'oracle metadata' => ['http://192.0.0.192/'],
            'ipv4-translated ipv6' => ['http://[::ffff:0:7f00:1]/'],
            'unspecified' => ['http://0.0.0.0'],
            'multicast' => ['http://224.0.0.1'],
            'broadcast' => ['http://255.255.255.255'],
            'obfuscated ip' => ['http://2130706433'],
            'non http scheme' => ['ftp://example.com'],
        ];
    }

    #[DataProvider('alwaysForbiddenUrls')]
    public function test_some_destinations_stay_blocked_even_when_private_targets_are_allowed(string $url): void
    {
        config(['pulsecheck.allow_private_targets' => true]);

        $this->expectException(UnsafeUrlException::class);

        $this->guard()->inspect($url);
    }
}
