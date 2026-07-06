<?php

namespace Tests\Unit;

use App\Services\CertificateExtractionService;
use PHPUnit\Framework\TestCase;

class CertificateParseTest extends TestCase
{
    private function svc(): CertificateExtractionService
    {
        return new CertificateExtractionService();
    }

    public function test_parses_clean_json(): void
    {
        $r = $this->svc()->parseExtraction('{"haccp_scadenza":"2027-06-30","moca_numero":"MOCA/2024/IT/00342"}');
        $this->assertTrue($r['ok']);
        $this->assertSame('2027-06-30', $r['haccp_scadenza']);
        $this->assertSame('MOCA/2024/IT/00342', $r['moca_numero']);
    }

    public function test_strips_markdown_fences_and_prose(): void
    {
        $text = "Ecco i dati estratti:\n```json\n{\"haccp_scadenza\": \"2026-12-31\", \"moca_numero\": \"ABC-123\"}\n```";
        $r = $this->svc()->parseExtraction($text);
        $this->assertTrue($r['ok']);
        $this->assertSame('2026-12-31', $r['haccp_scadenza']);
        $this->assertSame('ABC-123', $r['moca_numero']);
    }

    public function test_handles_null_fields(): void
    {
        $r = $this->svc()->parseExtraction('{"haccp_scadenza":null,"moca_numero":null}');
        $this->assertTrue($r['ok']);
        $this->assertNull($r['haccp_scadenza']);
        $this->assertNull($r['moca_numero']);
    }

    public function test_rejects_non_json(): void
    {
        $r = $this->svc()->parseExtraction('Non sono riuscito a leggere il documento.');
        $this->assertFalse($r['ok']);
    }

    public function test_normalizes_iso_datetime_to_date(): void
    {
        $r = $this->svc()->parseExtraction('{"haccp_scadenza":"2027-06-30T00:00:00Z","moca_numero":""}');
        $this->assertSame('2027-06-30', $r['haccp_scadenza']);
        $this->assertNull($r['moca_numero']); // empty string → null
    }
}
