<?php

namespace Tests\Unit\Security;

use App\Security\Totp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    private const RFC_SECRET = '12345678901234567890';

    public static function rfcVectors(): array
    {
        return [
            [59, '287082'],
            [1111111109, '081804'],
            [1111111111, '050471'],
            [1234567890, '005924'],
            [2000000000, '279037'],
        ];
    }

    #[DataProvider('rfcVectors')]
    public function test_it_matches_the_rfc_6238_test_vectors(int $timestamp, string $expected): void
    {
        $totp = new Totp;
        $secret = $totp->base32Encode(self::RFC_SECRET);

        $this->assertSame($expected, $totp->codeAt($secret, $totp->currentStep($timestamp)));
    }

    public function test_base32_round_trips(): void
    {
        $totp = new Totp;

        foreach (['', 'f', 'fo', 'foo', 'foob', 'fooba', 'foobar', random_bytes(20)] as $value) {
            $this->assertSame($value, $totp->base32Decode($totp->base32Encode($value)));
        }

        $this->assertSame('MZXW6YTBOI', $totp->base32Encode('foobar'));
    }

    public function test_it_accepts_one_step_of_drift_and_rejects_reuse(): void
    {
        $totp = new Totp;
        $secret = $totp->generateSecret();
        $now = 1_800_000_000;
        $step = $totp->currentStep($now);

        $this->assertSame($step - 1, $totp->matchingStep($secret, $totp->codeAt($secret, $step - 1), null, $now));
        $this->assertSame($step + 1, $totp->matchingStep($secret, $totp->codeAt($secret, $step + 1), null, $now));
        $this->assertNull($totp->matchingStep($secret, $totp->codeAt($secret, $step - 2), null, $now));
        $this->assertNull($totp->matchingStep($secret, $totp->codeAt($secret, $step), $step, $now));
        $this->assertNull($totp->matchingStep($secret, 'abcdef', null, $now));
        $this->assertSame($step, $totp->matchingStep($secret, chunk_split($totp->codeAt($secret, $step), 3, ' '), null, $now));
    }

    public function test_provisioning_uri_and_qr_code(): void
    {
        $totp = new Totp;
        $uri = $totp->provisioningUri('JBSWY3DPEHPK3PXP', 'ada@example.com', 'Acme Ops');

        $this->assertSame('otpauth://totp/Acme%20Ops:ada%40example.com?secret=JBSWY3DPEHPK3PXP&issuer=Acme%20Ops&algorithm=SHA1&digits=6&period=30', $uri);
        $this->assertStringStartsWith('<svg', $totp->qrCodeSvg($uri));
    }
}
