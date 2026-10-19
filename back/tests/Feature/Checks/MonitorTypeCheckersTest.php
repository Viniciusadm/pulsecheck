<?php

namespace Tests\Feature\Checks;

use App\Enums\CheckErrorType;
use App\Enums\MonitorType;
use App\Enums\WarningType;
use App\Models\Monitor;
use App\Security\Exceptions\SocketProbeException;
use App\Services\CheckOutcome;
use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\FakesDns;
use Tests\Concerns\FakesOutboundHttp;
use Tests\Concerns\FakesSockets;
use Tests\TestCase;

class MonitorTypeCheckersTest extends TestCase
{
    use FakesDns;
    use FakesOutboundHttp;
    use FakesSockets;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-17 12:00:00'));
        $this->fakeDns([
            'example.com' => ['93.184.215.14'],
            'db.example.com' => ['93.184.215.40'],
            'internal.example.com' => ['10.0.0.5'],
        ]);
    }

    private function check(array $attributes): ?CheckOutcome
    {
        $monitor = Monitor::factory()->make(['timeout_seconds' => 10, ...$attributes]);

        return $monitor->type->checker()->check($monitor);
    }

    public static function keywordCases(): array
    {
        return [
            'contains, found' => ['contains', false, 'Service is OPERATIONAL', true, null],
            'contains, missing' => ['contains', false, 'Service is degraded', false, CheckErrorType::KeywordMissing],
            'contains, case sensitive mismatch' => ['contains', true, 'Service is OPERATIONAL', false, CheckErrorType::KeywordMissing],
            'not contains, absent' => ['not_contains', false, 'All good', true, null],
            'not contains, present' => ['not_contains', false, 'There was an Operational error', false, CheckErrorType::KeywordPresent],
        ];
    }

    #[DataProvider('keywordCases')]
    public function test_keyword_checks(string $mode, bool $caseSensitive, string $body, bool $isSuccess, ?CheckErrorType $errorType): void
    {
        $this->fakeOutboundHttp(new Response(200, [], $body));

        $outcome = $this->check([
            'type' => MonitorType::Keyword,
            'url' => 'https://example.com/status',
            'options' => ['keyword' => 'operational', 'keyword_mode' => $mode, 'case_sensitive' => $caseSensitive],
        ]);

        $this->assertSame($isSuccess, $outcome->isSuccess);
        $this->assertSame($errorType, $outcome->errorType);
    }

    public function test_keyword_status_mismatch_wins_over_the_body(): void
    {
        $this->fakeOutboundHttp(new Response(500, [], 'operational'));

        $outcome = $this->check(['type' => MonitorType::Keyword, 'url' => 'https://example.com', 'options' => ['keyword' => 'operational']]);

        $this->assertSame(CheckErrorType::HttpStatus, $outcome->errorType);
    }

    public function test_keyword_search_is_limited_to_the_body_cap(): void
    {
        config(['pulsecheck.keyword_max_body_bytes' => 10]);
        $this->fakeOutboundHttp(new Response(200, [], str_repeat('x', 20).'operational'));

        $outcome = $this->check(['type' => MonitorType::Keyword, 'url' => 'https://example.com', 'options' => ['keyword' => 'operational']]);

        $this->assertSame(CheckErrorType::KeywordMissing, $outcome->errorType);
    }

    public function test_tcp_connects_to_the_pinned_ip(): void
    {
        $this->fakeSockets(null);

        $outcome = $this->check(['type' => MonitorType::Tcp, 'url' => null, 'options' => ['host' => 'db.example.com', 'port' => 5432]]);

        $this->assertTrue($outcome->isSuccess);
        $this->assertIsInt($outcome->latencyMs);
        $this->assertSame('tcp://93.184.215.40:5432', $this->socketProbes[0]['address']);
        $this->assertSame([], $this->socketProbes[0]['ssl']);
    }

    public static function tcpFailures(): array
    {
        return [
            'refused' => [new SocketProbeException(SocketProbeException::REFUSED, 'Connection refused'), CheckErrorType::Connection],
            'timeout' => [new SocketProbeException(SocketProbeException::TIMEOUT, 'Connection timed out'), CheckErrorType::Timeout],
        ];
    }

    #[DataProvider('tcpFailures')]
    public function test_tcp_failures(SocketProbeException $exception, CheckErrorType $errorType): void
    {
        $this->fakeSockets($exception);

        $outcome = $this->check(['type' => MonitorType::Tcp, 'url' => null, 'options' => ['host' => 'db.example.com', 'port' => 5432]]);

        $this->assertFalse($outcome->isSuccess);
        $this->assertSame($errorType, $outcome->errorType);
    }

    public function test_tcp_to_a_private_address_is_blocked_without_connecting(): void
    {
        $this->fakeSockets();

        $outcome = $this->check(['type' => MonitorType::Tcp, 'url' => null, 'options' => ['host' => 'internal.example.com', 'port' => 22]]);

        $this->assertSame(CheckErrorType::BlockedDestination, $outcome->errorType);
        $this->assertCount(0, $this->socketProbes);
    }

    public function test_ssl_records_the_expiry_and_verifies_the_host_name(): void
    {
        $this->fakeSockets(['validTo_time_t' => now()->addDays(60)->getTimestamp()]);

        $outcome = $this->check(['type' => MonitorType::Ssl, 'url' => 'https://example.com:8443/']);

        $this->assertTrue($outcome->isSuccess);
        $this->assertFalse($outcome->isDegraded);
        $this->assertTrue($outcome->certificateExpiresAt->equalTo(now()->addDays(60)));
        $this->assertSame('ssl://93.184.215.14:8443', $this->socketProbes[0]['address']);
        $this->assertSame('example.com', $this->socketProbes[0]['ssl']['peer_name']);
        $this->assertTrue($this->socketProbes[0]['ssl']['verify_peer']);
    }

    public function test_ssl_certificate_inside_the_warning_window_is_degraded(): void
    {
        $this->fakeSockets(['validTo_time_t' => now()->addDays(5)->getTimestamp()]);

        $outcome = $this->check(['type' => MonitorType::Ssl, 'url' => 'https://example.com', 'options' => ['warning_days' => 7]]);

        $this->assertTrue($outcome->isSuccess);
        $this->assertTrue($outcome->isDegraded);
        $this->assertSame(WarningType::CertificateExpiring, $outcome->warningType);
        $this->assertSame('The certificate expires in 5 days.', $outcome->warningMessage);
    }

    public function test_expired_certificate_fails_with_its_date(): void
    {
        $this->fakeSockets(
            new SocketProbeException(SocketProbeException::TLS, 'certificate verify failed'),
            ['validTo_time_t' => now()->subDays(2)->getTimestamp()],
        );

        $outcome = $this->check(['type' => MonitorType::Ssl, 'url' => 'https://example.com']);

        $this->assertFalse($outcome->isSuccess);
        $this->assertSame(CheckErrorType::CertificateExpired, $outcome->errorType);
        $this->assertSame('The certificate expired on 2026-10-15.', $outcome->errorMessage);
        $this->assertFalse($this->socketProbes[1]['ssl']['verify_peer']);
    }

    public function test_untrusted_certificate_fails_as_tls_error(): void
    {
        $this->fakeSockets(
            new SocketProbeException(SocketProbeException::TLS, 'certificate verify failed'),
            ['validTo_time_t' => now()->addYear()->getTimestamp()],
        );

        $outcome = $this->check(['type' => MonitorType::Ssl, 'url' => 'https://example.com']);

        $this->assertSame(CheckErrorType::Tls, $outcome->errorType);
        $this->assertSame('certificate verify failed', $outcome->errorMessage);
    }

    public function test_heartbeat_fails_only_after_the_window_without_ping(): void
    {
        $base = ['type' => MonitorType::Heartbeat, 'url' => null, 'interval_minutes' => 5, 'options' => ['grace_seconds' => 60]];

        $this->assertNull($this->check([...$base, 'last_heartbeat_at' => now()->subMinutes(5)]));

        $missed = $this->check([...$base, 'last_heartbeat_at' => now()->subMinutes(7)]);
        $this->assertSame(CheckErrorType::HeartbeatMissed, $missed->errorType);

        $never = $this->check([...$base, 'last_heartbeat_at' => null]);
        $this->assertSame('No ping was received yet.', $never->errorMessage);
    }
}
