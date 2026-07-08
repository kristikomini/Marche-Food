<?php

namespace Tests\Feature;

use App\Http\Controllers\ProduzioneController;
use App\Models\Acquisto;
use App\Models\AcquistoRiga;
use App\Models\Cliente;
use App\Models\Fornitore;
use App\Models\LottoSemilavorato;
use App\Models\MateriaPrima;
use App\Models\Prodotto;
use App\Models\Produzione;
use App\Models\SchedaProduzione;
use App\Models\User;
use App\Models\Vendita;
use App\Models\VenditaRiga;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_balances_account_for_production_and_direct_sales(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $forn = Fornitore::create(['ragione_sociale' => 'F', 'tipo' => 'alimentare']);
        $prod = Prodotto::create(['nome' => 'Tonno', 'attivo' => true]);
        $mp   = MateriaPrima::create(['nome' => 'Tonno']);
        $acq  = Acquisto::create(['fornitore_id' => $forn->id, 'numero_documento' => 'D1', 'data_documento' => '2026-06-01', 'tipo_documento' => 'DDT']);
        $riga = AcquistoRiga::create(['acquisto_id' => $acq->id, 'nome_prodotto' => 'Tonno', 'quantita_kg' => 100, 'lotto' => 'L1', 'data_in' => '2026-06-01']);
        $scheda = SchedaProduzione::create(['prodotto_id' => $prod->id, 'modello' => 'M1', 'revisione' => 0, 'data_revisione' => '2026-06-01', 'attiva' => true]);
        $scheda->ricette()->create(['materia_prima_id' => $mp->id, 'ordine' => 1]);

        (new ProduzioneController())->store(Request::create('/produzioni', 'POST', [
            'scheda_id' => $scheda->id, 'lotto_produzione' => 'LP1', 'data_produzione' => '2026-06-10',
            'materie_prime' => [['materia_prima_id' => $mp->id, 'source_type' => 'acquisto', 'acquisto_riga_id' => $riga->id, 'quantita_kg' => 60]],
        ]));

        $cli = Cliente::create(['codice_cliente' => 'C1', 'ragione_sociale' => 'Cliente']);
        $vend = Vendita::create(['cliente_id' => $cli->id, 'numero_documento' => 'V1', 'data_documento' => '2026-06-12', 'tipo_documento' => 'DDT']);
        VenditaRiga::create(['vendita_id' => $vend->id, 'nome_prodotto' => 'Tonno', 'quantita_kg' => 10, 'lotto' => 'L1', 'acquisto_riga_id' => $riga->id]);

        $inv = new InventoryService();
        $bal = $inv->purchaseLotBalances(false)->firstWhere('id', $riga->id);

        $this->assertEqualsWithDelta(60, $bal->consumato_kg, 0.001);
        $this->assertEqualsWithDelta(10, $bal->venduto_kg, 0.001);
        $this->assertEqualsWithDelta(30, $bal->balance_kg, 0.001);
    }

    public function test_semilavorato_balance_and_summary(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $forn = Fornitore::create(['ragione_sociale' => 'F', 'tipo' => 'alimentare']);
        $prod = Prodotto::create(['nome' => 'Tonno', 'attivo' => true]);
        $mp   = MateriaPrima::create(['nome' => 'Tonno']);
        $acq  = Acquisto::create(['fornitore_id' => $forn->id, 'numero_documento' => 'D1', 'data_documento' => '2026-06-01', 'tipo_documento' => 'DDT']);
        $riga = AcquistoRiga::create(['acquisto_id' => $acq->id, 'nome_prodotto' => 'Tonno', 'quantita_kg' => 100, 'lotto' => 'L1', 'data_in' => '2026-06-01']);
        $scheda = SchedaProduzione::create(['prodotto_id' => $prod->id, 'modello' => 'M1', 'revisione' => 0, 'data_revisione' => '2026-06-01', 'attiva' => true]);
        $scheda->ricette()->create(['materia_prima_id' => $mp->id, 'ordine' => 1]);
        $ctrl = new ProduzioneController();

        $ctrl->store(Request::create('/produzioni', 'POST', [
            'scheda_id' => $scheda->id, 'lotto_produzione' => 'LP1', 'data_produzione' => '2026-06-10',
            'materie_prime' => [['materia_prima_id' => $mp->id, 'source_type' => 'acquisto', 'acquisto_riga_id' => $riga->id, 'quantita_kg' => 60]],
        ]));
        $base = Produzione::where('lotto_produzione', 'LP1')->first();
        $ctrl->storeSemilavorato(Request::create('/x', 'POST', ['lotto' => 'S1', 'nome_prodotto' => 'Semi', 'quantita_kg' => 30]), $base);
        $semi = LottoSemilavorato::where('lotto', 'S1')->first();
        $ctrl->store(Request::create('/produzioni', 'POST', [
            'scheda_id' => $scheda->id, 'lotto_produzione' => 'LP2', 'data_produzione' => '2026-06-13',
            'materie_prime' => [['materia_prima_id' => $mp->id, 'source_type' => 'interno', 'semilavorato_id' => $semi->id, 'quantita_kg' => 20]],
        ]));

        $inv  = new InventoryService();
        $sbal = $inv->semilavoratoBalances(false)->firstWhere('id', $semi->id);
        $this->assertEqualsWithDelta(10, $sbal->balance_kg, 0.001);

        $summary = $inv->summary();
        $this->assertSame(1, $summary['lotti_acquisto']);
        $this->assertEqualsWithDelta(10, $summary['kg_giacenza_semilavorato'], 0.001);
    }
}
