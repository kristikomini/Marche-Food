<?php

namespace Tests\Feature;

use App\Models\Fornitore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * HTTP feature tests for the CSV import (Epic 3), focused on the all-or-nothing
 * transactional behavior (GAP-T2).
 */
class ImportHttpTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'fornitore_codice;numero_documento;data_documento;tipo_documento;nome_prodotto;quantita_kg;quantita_pz;lotto;lotto_esterno;scadenza;data_in;note_documento';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
        Fornitore::create(['codice' => 'F1', 'ragione_sociale' => 'Pesca', 'tipo' => 'alimentare']);
    }

    private function csv(array $lines): UploadedFile
    {
        $content = implode("\n", array_merge([self::HEADER], $lines)) . "\n";
        return UploadedFile::fake()->createWithContent('acquisti.csv', $content);
    }

    public function test_valid_csv_imports_documents_and_lines(): void
    {
        $file = $this->csv([
            'F1;DDT1;01/06/2026;DDT;Tonno;10;;L1;;;01/06/2026;',
            'F1;DDT1;01/06/2026;DDT;Sgombro;5;;L2;;;01/06/2026;',
        ]);

        $this->post('/import/acquisti', ['file' => $file])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('acquisti', ['numero_documento' => 'DDT1']);
        $this->assertDatabaseCount('acquisti_righe', 2);
    }

    public function test_import_is_all_or_nothing_on_error(): void
    {
        // Second document references a non-existent supplier → whole import rolls back.
        $file = $this->csv([
            'F1;DDT1;01/06/2026;DDT;Tonno;10;;L1;;;01/06/2026;',
            'NOEXIST;DDT2;02/06/2026;DDT;X;5;;L2;;;02/06/2026;',
        ]);

        $this->post('/import/acquisti', ['file' => $file])
            ->assertSessionHas('error');

        // Nothing committed — the valid document was rolled back too.
        $this->assertDatabaseCount('acquisti', 0);
        $this->assertDatabaseCount('acquisti_righe', 0);
    }

    public function test_import_requires_admin(): void
    {
        $file = $this->csv(['F1;DDT1;01/06/2026;DDT;Tonno;10;;L1;;;01/06/2026;']);

        $this->actingAs(User::factory()->operator()->create())
            ->post('/import/acquisti', ['file' => $file])
            ->assertRedirect('/');

        $this->assertDatabaseCount('acquisti', 0);
    }
}
