<?php

namespace Tests\Feature;

use App\Models\Acquisto;
use App\Models\AcquistoRiga;
use App\Models\Fornitore;
use App\Models\MateriaPrima;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gap B — incoming purchase lots can link to a raw material, so allergens flow
 * from the anagrafica onto received lots (labels + traceability).
 */
class PurchaseLotAllergenTest extends TestCase
{
    use RefreshDatabase;

    private function linkedLot(): array
    {
        $mp = MateriaPrima::create(['nome' => 'Gamberi', 'allergeni' => ['crostacei'], 'allergeni_tracce' => ['pesce']]);
        $fornitore = Fornitore::create(['ragione_sociale' => 'F', 'tipo' => 'alimentare']);
        $acquisto = Acquisto::create(['fornitore_id' => $fornitore->id, 'numero_documento' => 'D1', 'data_documento' => '2026-06-01', 'tipo_documento' => 'DDT']);
        $riga = AcquistoRiga::create([
            'acquisto_id' => $acquisto->id, 'materia_prima_id' => $mp->id,
            'nome_prodotto' => 'Gamberi', 'quantita_kg' => 10, 'lotto' => 'L1', 'data_in' => '2026-06-01',
        ]);

        return [$mp, $acquisto, $riga];
    }

    public function test_store_persists_materia_prima_link(): void
    {
        $admin = User::factory()->admin()->create();
        $mp = MateriaPrima::create(['nome' => 'Tonno']);
        $fornitore = Fornitore::create(['ragione_sociale' => 'F', 'tipo' => 'alimentare']);

        $this->actingAs($admin)->post('/acquisti', [
            'fornitore_id' => $fornitore->id, 'numero_documento' => 'D9', 'data_documento' => '2026-06-01', 'tipo_documento' => 'DDT',
            'righe' => [[
                'materia_prima_id' => $mp->id, 'nome_prodotto' => 'Tonno',
                'quantita_kg' => 5, 'data_in' => '2026-06-01',
            ]],
        ])->assertRedirect('/acquisti');

        $this->assertDatabaseHas('acquisti_righe', ['materia_prima_id' => $mp->id, 'nome_prodotto' => 'Tonno']);
    }

    public function test_purchase_lot_labels_show_linked_allergens(): void
    {
        $admin = User::factory()->admin()->create();
        [, $acquisto] = $this->linkedLot();

        $this->actingAs($admin)->get("/acquisti/{$acquisto->id}/etichette")
            ->assertOk()
            ->assertSee('Crostacei')
            ->assertSee('Pesce');
    }

    public function test_traceability_shows_purchase_lot_allergens(): void
    {
        $admin = User::factory()->admin()->create();
        $this->linkedLot();

        $this->actingAs($admin)->get('/tracciabilita/search?q=L1')
            ->assertOk()
            ->assertSee('Crostacei');
    }
}
