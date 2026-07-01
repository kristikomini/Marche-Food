<?php

namespace Tests\Feature;

use App\Models\Acquisto;
use App\Models\AcquistoRiga;
use App\Models\Cliente;
use App\Models\Fornitore;
use App\Models\MateriaPrima;
use App\Models\Prodotto;
use App\Models\Produzione;
use App\Models\SchedaProduzione;
use App\Models\User;
use App\Models\Vendita;
use App\Models\VenditaRiga;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * HTTP feature tests for the traceability search (Epic 3): the three legs
 * (purchase lots, productions, sales) resolve for a lot code.
 */
class TracciabilitaHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->operator()->create());
    }

    public function test_search_returns_matching_legs_for_a_lot(): void
    {
        $forn = Fornitore::create(['ragione_sociale' => 'Pesca', 'tipo' => 'alimentare']);
        $prod = Prodotto::create(['codice_prodotto' => 'P1', 'nome' => 'Tonno', 'attivo' => true]);
        $acq  = Acquisto::create(['fornitore_id' => $forn->id, 'numero_documento' => 'D1', 'data_documento' => '2026-06-01', 'tipo_documento' => 'DDT']);
        AcquistoRiga::create(['acquisto_id' => $acq->id, 'nome_prodotto' => 'Tonno', 'quantita_kg' => 50, 'lotto' => 'TRACE-1', 'data_in' => '2026-06-01']);

        $scheda = SchedaProduzione::create(['prodotto_id' => $prod->id, 'modello' => 'M1', 'revisione' => 0, 'data_revisione' => '2026-06-01', 'attiva' => true]);
        Produzione::create(['scheda_id' => $scheda->id, 'lotto_produzione' => 'TRACE-1', 'data_produzione' => '2026-06-10']);

        $cli = Cliente::create(['codice_cliente' => 'C1', 'ragione_sociale' => 'Cliente']);
        $v = Vendita::create(['cliente_id' => $cli->id, 'numero_documento' => 'V1', 'data_documento' => '2026-06-12', 'tipo_documento' => 'DDT']);
        VenditaRiga::create(['vendita_id' => $v->id, 'nome_prodotto' => 'Tonno', 'quantita_kg' => 10, 'lotto' => 'TRACE-1']);

        $this->get('/tracciabilita/search?q=TRACE-1')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tracciabilita')
                ->where('query', 'TRACE-1')
                ->has('risultati.righe_acquisto', 1)
                ->has('risultati.produzioni', 1)
                ->has('risultati.vendite_righe', 1)
            );
    }

    public function test_short_query_returns_no_results(): void
    {
        $this->get('/tracciabilita/search?q=a')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tracciabilita')
                ->where('risultati', null)
            );
    }
}
