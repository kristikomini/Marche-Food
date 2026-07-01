<?php

namespace Tests\Unit;

use App\Http\Middleware\SecurityHeaders;
use PHPUnit\Framework\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_exposes_the_expected_baseline_headers(): void
    {
        $headers = SecurityHeaders::headers();

        $this->assertSame('nosniff', $headers['X-Content-Type-Options']);
        $this->assertSame('SAMEORIGIN', $headers['X-Frame-Options']);
        $this->assertArrayHasKey('Referrer-Policy', $headers);
        $this->assertArrayHasKey('X-XSS-Protection', $headers);
    }
}
