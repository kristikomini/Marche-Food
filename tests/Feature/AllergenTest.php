<?php

namespace Tests\Feature;

use App\Models\Acquisto;
use App\Models\AcquistoRiga;
use App\Models\Fornitore;
use App\Models\MateriaPrima;
use App\Models\Prodotto;
use App\Models\Produzione;
use App\Models\SchedaProduzione;
use App\Models\User;
use App\Services\AllergenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3 — allergen tracking (EU Reg. 1169/2011). Verifies persistence,
 * validation of the 14-allergen whitelist, and the recursive propagation of
 * allergens from raw materials up through semi-finished ingredients.
 */
class AllergenTest extends TestCase
{
    use RefreshDatabase;

    public function test_materia_prima_persists_allergens(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/materie-prime', [
            'nome'             => 'Gamberi',
            'allergeni'        => ['crostacei'],
            'allergeni_tracce' => ['pesce'],
        ])->assertRedirect('/materie-prime');

        $mp = MateriaPrima::where('nome', 'Gamberi')->firstOrFail();
        $this->assertEquals(['crostacei'], $mp->allergeni);
        $this->assertEquals(['pesce'], $mp->allergeni_tracce);
    }

    public function test_invalid_allergen_code_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/materie-prime', [
            'nome'      => 'X',
            'allergeni' => ['not_a_real_allergen'],
        ])->assertSessionHasErrors('allergeni.0');
    }

    public function test_production_lot_derives_allergens_recursively_through_semilavorato(): void
    {
        $tonno   = MateriaPrima::create(['nome' => 'Tonno', 'allergeni' => ['pesce'], 'allergeni_tracce' => ['crostacei']]);
        $glutine = MateriaPrima::create(['nome' => 'Pangrattato', 'allergeni' => ['cereali_glutine']]);
        $sale    = MateriaPrima::create(['nome' => 'Sale']); // no allergens

        $fornitore = Fornitore::create(['ragione_sociale' => 'F', 'tipo' => 'alimentare']);
        $prodotto  = Prodotto::create(['nome' => 'Polpetta', 'attivo' => true]);
        $scheda    = SchedaProduzione::create(['prodotto_id' => $prodotto->id, 'modello' => 'M', 'revisione' => 0, 'data_revisione' => '2026-06-01', 'attiva' => true]);
        $acq       = Acquisto::create(['fornitore_id' => $fornitore->id, 'numero_documento' => 'D', 'data_documento' => '2026-06-01', 'tipo_documento' => 'DDT']);
        $riga      = AcquistoRiga::create(['acquisto_id' => $acq->id, 'nome_prodotto' => 'Tonno', 'quantita_kg' => 100, 'lotto' => 'L1', 'data_in' => '2026-06-01']);

        // Base production uses tonno → produces a semilavorato
        $base = Produzione::create(['scheda_id' => $scheda->id, 'lotto_produzione' => 'BASE', 'data_produzione' => '2026-06-02']);
        $base->materiePrime()->create(['acquisto_riga_id' => $riga->id, 'materia_prima_id' => $tonno->id, 'quantita_kg' => 10]);
        $semi = $base->lottoSemilavorato()->create(['lotto' => 'SEMI', 'nome_prodotto' => 'Base', 'quantita_kg' => 8, 'data_produzione' => '2026-06-02']);

        // Downstream production consumes the semilavorato (recorded under a neutral
        // material) + pangrattato. Only recursion into BASE surfaces fish/shellfish.
        $down = Produzione::create(['scheda_id' => $scheda->id, 'lotto_produzione' => 'DOWN', 'data_produzione' => '2026-06-03']);
        $down->materiePrime()->create(['semilavorato_id' => $semi->id, 'materia_prima_id' => $sale->id, 'quantita_kg' => 2]);
        $down->materiePrime()->create(['acquisto_riga_id' => $riga->id, 'materia_prima_id' => $glutine->id, 'quantita_kg' => 1]);

        $result = app(AllergenService::class)->forProduzione($down->fresh());

        sort($result['contiene']);
        $this->assertEquals(['cereali_glutine', 'pesce'], $result['contiene']);
        // crostacei is only a "may contain" from tonno (via the semilavorato)
        $this->assertEquals(['crostacei'], $result['tracce']);
    }

    public function test_trace_is_dropped_when_also_a_declared_contains(): void
    {
        $mp = MateriaPrima::create(['nome' => 'Misto', 'allergeni' => ['latte'], 'allergeni_tracce' => ['latte', 'soia']]);

        $prodotto = Prodotto::create(['nome' => 'Crema', 'attivo' => true]);
        $scheda   = SchedaProduzione::create(['prodotto_id' => $prodotto->id, 'modello' => 'M2', 'revisione' => 0, 'data_revisione' => '2026-06-01', 'attiva' => true]);
        $prod     = Produzione::create(['scheda_id' => $scheda->id, 'lotto_produzione' => 'C1', 'data_produzione' => '2026-06-04']);
        $prod->materiePrime()->create(['materia_prima_id' => $mp->id, 'quantita_kg' => 5]);

        $result = app(AllergenService::class)->forProduzione($prod->fresh());

        $this->assertEquals(['latte'], $result['contiene']);
        $this->assertEquals(['soia'], $result['tracce']); // latte removed from tracce
    }
}
