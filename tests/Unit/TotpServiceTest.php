<?php

namespace Tests\Unit;

use App\Services\TotpService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TotpServiceTest extends TestCase
{
    private function svc(): TotpService
    {
        return new TotpService();
    }

    public function test_base32_roundtrips(): void
    {
        $svc = $this->svc();
        $raw = '12345678901234567890';
        $this->assertSame($raw, $svc->base32Decode($svc->base32Encode($raw)));
    }

    #[DataProvider('rfcVectors')]
    public function test_matches_rfc6238_vectors(int $time, string $expected): void
    {
        $svc = $this->svc();
        $secret = $svc->base32Encode('12345678901234567890');
        $this->assertSame($expected, $svc->codeAt($secret, $time, 8));
    }

    public static function rfcVectors(): array
    {
        return [
            [59, '94287082'],
            [1111111109, '07081804'],
            [1111111111, '14050471'],
            [1234567890, '89005924'],
            [2000000000, '69279037'],
            [20000000000, '65353130'],
        ];
    }

    public function test_verify_accepts_current_and_rejects_wrong(): void
    {
        $svc = $this->svc();
        $secret = $svc->generateSecret();
        $now = 1700000000;
        $code = $svc->codeAt($secret, $now);
        $this->assertTrue($svc->verify($secret, $code, 1, $now));
        $this->assertFalse($svc->verify($secret, 'abc', 1, $now));
    }

    public function test_verify_tolerates_one_period_drift(): void
    {
        $svc = $this->svc();
        $secret = $svc->generateSecret();
        $now = 1700000000;
        $this->assertTrue($svc->verify($secret, $svc->codeAt($secret, $now - 30), 1, $now));
        $this->assertTrue($svc->verify($secret, $svc->codeAt($secret, $now + 30), 1, $now));
    }
}
