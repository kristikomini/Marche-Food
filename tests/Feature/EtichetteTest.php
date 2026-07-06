<?php

namespace Tests\Feature;

use App\Models\Acquisto;
use App\Models\AcquistoRiga;
use App\Models\Cliente;
use App\Models\Fornitore;
use App\Models\User;
use App\Models\Vendita;
use App\Models\VenditaRiga;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 — QR lot labels for purchases and sales.
 */
class EtichetteTest extends TestCase
{
    use RefreshDatabase;

    public function test_acquisto_labels_render_with_lot_and_trace_link(): void
    {
        $admin = User::factory()->admin()->create();
        $fornitore = Fornitore::create(['ragione_sociale' => 'Pesca SRL', 'tipo' => 'alimentare']);
        $acquisto = Acquisto::create(['fornitore_id' => $fornitore->id, 'numero_documento' => 'D1', 'data_documento' => '2026-06-01', 'tipo_documento' => 'DDT']);
        AcquistoRiga::create(['acquisto_id' => $acquisto->id, 'nome_prodotto' => 'Tonno', 'quantita_kg' => 50, 'lotto' => 'L100', 'data_in' => '2026-06-01']);

        $res = $this->actingAs($admin)->get("/acquisti/{$acquisto->id}/etichette?copie=2");

        $res->assertOk();
        $res->assertSee('L100');
        $res->assertSee('tracciabilita?q=L100', false);
        // copie=2 → exactly two QR labels are rendered
        $this->assertEquals(2, substr_count($res->getContent(), 'data-qr='));
    }

    public function test_vendita_labels_render(): void
    {
        $admin = User::factory()->admin()->create();
        $cliente = Cliente::create(['ragione_sociale' => 'Ristorante', 'codice_cliente' => 'C1', 'attivo' => true]);
        $vendita = Vendita::create(['cliente_id' => $cliente->id, 'numero_documento' => 'V1', 'data_documento' => '2026-06-02', 'tipo_documento' => 'DDT']);
        VenditaRiga::create(['vendita_id' => $vendita->id, 'nome_prodotto' => 'Tonno', 'quantita_kg' => 5, 'lotto' => 'LP-2026-01']);

        $this->actingAs($admin)->get("/vendite/{$vendita->id}/etichette")
            ->assertOk()
            ->assertSee('LP-2026-01');
    }

    public function test_lines_without_lot_are_skipped(): void
    {
        $admin = User::factory()->admin()->create();
        $fornitore = Fornitore::create(['ragione_sociale' => 'Pesca SRL', 'tipo' => 'alimentare']);
        $acquisto = Acquisto::create(['fornitore_id' => $fornitore->id, 'numero_documento' => 'D2', 'data_documento' => '2026-06-01', 'tipo_documento' => 'DDT']);
        AcquistoRiga::create(['acquisto_id' => $acquisto->id, 'nome_prodotto' => 'Senza lotto', 'quantita_kg' => 10, 'data_in' => '2026-06-01']);

        $this->actingAs($admin)->get("/acquisti/{$acquisto->id}/etichette")
            ->assertOk()
            ->assertSee('Nessun lotto con codice');
    }
}
