<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CertificateExtractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_extract_with_faked_provider(): void
    {
        config(['ai.anthropic.key' => 'test-key', 'ai.anthropic.model' => 'claude-sonnet-5', 'ai.anthropic.base' => 'https://api.anthropic.com']);
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '{"haccp_scadenza":"2028-01-15","moca_numero":"MOCA-1"}']],
        ], 200)]);

        $this->actingAs(User::factory()->admin()->create())
            ->post('/fornitori/estrai-certificato', ['file' => UploadedFile::fake()->create('cert.pdf', 20, 'application/pdf')])
            ->assertOk()
            ->assertJson(['ok' => true, 'haccp_scadenza' => '2028-01-15', 'moca_numero' => 'MOCA-1']);
    }

    public function test_returns_422_when_not_configured(): void
    {
        config(['ai.anthropic.key' => null]);

        $this->actingAs(User::factory()->admin()->create())
            ->post('/fornitori/estrai-certificato', ['file' => UploadedFile::fake()->create('cert.pdf', 20, 'application/pdf')])
            ->assertStatus(422);
    }

    public function test_requires_admin(): void
    {
        $this->actingAs(User::factory()->operator()->create())
            ->post('/fornitori/estrai-certificato', ['file' => UploadedFile::fake()->create('cert.pdf', 20, 'application/pdf')])
            ->assertRedirect('/');
    }
}
