<?php

namespace Tests\Feature;

use App\Http\Controllers\ProduzioneController;
use App\Models\Acquisto;
use App\Models\AcquistoRiga;
use App\Models\Fornitore;
use App\Models\LottoSemilavorato;
use App\Models\MateriaPrima;
use App\Models\Prodotto;
use App\Models\Produzione;
use App\Models\SchedaProduzione;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Exercises the lot-balance enforcement in ProduzioneController by calling the
 * controller directly (not via the HTTP kernel), which keeps the test runnable
 * both in CI and in constrained runtimes.
 */
class ProduzioneBalanceTest extends TestCase
{
    use RefreshDatabase;

    private ProduzioneController $controller;
    private MateriaPrima $mp;
    private SchedaProduzione $scheda;
    private AcquistoRiga $riga;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new ProduzioneController();

        $this->actingAs(User::factory()->admin()->create());

        $fornitore = Fornitore::create(['ragione_sociale' => 'Pesca SRL', 'tipo' => 'alimentare']);
        $prodotto  = Prodotto::create(['nome' => 'Tonno', 'attivo' => true]);
        $this->mp  = MateriaPrima::create(['nome' => 'Tonno fresco']);

        $acquisto = Acquisto::create([
            'fornitore_id'     => $fornitore->id,
            'numero_documento' => 'DDT1',
            'data_documento'   => '2026-06-01',
            'tipo_documento'   => 'DDT',
        ]);
        $this->riga = AcquistoRiga::create([
            'acquisto_id'   => $acquisto->id,
            'nome_prodotto' => 'Tonno fresco',
            'quantita_kg'   => 100,
            'lotto'         => 'L100',
            'data_in'       => '2026-06-01',
        ]);

        $this->scheda = SchedaProduzione::create([
            'prodotto_id'    => $prodotto->id,
            'modello'        => 'M1',
            'revisione'      => 0,
            'data_revisione' => '2026-06-01',
            'attiva'         => true,
        ]);
        $this->scheda->ricette()->create(['materia_prima_id' => $this->mp->id, 'ordine' => 1]);
    }

    private function purchasePayload(string $lotto, float $qty): Request
    {
        return Request::create('/produzioni', 'POST', [
            'scheda_id'        => $this->scheda->id,
            'lotto_produzione' => $lotto,
            'data_produzione'  => '2026-06-10',
            'materie_prime'    => [[
                'materia_prima_id' => $this->mp->id,
                'source_type'      => 'acquisto',
                'acquisto_riga_id' => $this->riga->id,
                'quantita_kg'      => $qty,
            ]],
        ]);
    }

    public function test_production_within_balance_succeeds(): void
    {
        $this->controller->store($this->purchasePayload('LP1', 60));

        $this->assertDatabaseHas('produzioni', ['lotto_produzione' => 'LP1']);
        $this->assertEqualsWithDelta(
            60,
            (float) \DB::table('produzioni_materie_prime')->where('acquisto_riga_id', $this->riga->id)->sum('quantita_kg'),
            0.001
        );
    }

    public function test_overdraw_is_rejected_and_not_persisted(): void
    {
        $this->controller->store($this->purchasePayload('LP1', 60));

        try {
            $this->controller->store($this->purchasePayload('LP2', 50)); // only 40 remain
            $this->fail('Expected ValidationException for over-draw');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('materie_prime', $e->errors());
        }

        $this->assertDatabaseMissing('produzioni', ['lotto_produzione' => 'LP2']);
    }

    public function test_semilavorato_balance_is_enforced(): void
    {
        // Base production consumes 60 kg of the purchase lot.
        $this->controller->store($this->purchasePayload('BASE', 60));
        $base = Produzione::where('lotto_produzione', 'BASE')->first();

        // Register a 30 kg semi-finished lot from it.
        $this->controller->storeSemilavorato(
            Request::create('/x', 'POST', ['lotto' => 'SEMI1', 'nome_prodotto' => 'Semi', 'quantita_kg' => 30]),
            $base
        );
        $semi = LottoSemilavorato::where('lotto', 'SEMI1')->firstOrFail();

        $internalPayload = fn (string $lotto, float $qty) => Request::create('/produzioni', 'POST', [
            'scheda_id'        => $this->scheda->id,
            'lotto_produzione' => $lotto,
            'data_produzione'  => '2026-06-11',
            'materie_prime'    => [[
                'materia_prima_id' => $this->mp->id,
                'source_type'      => 'interno',
                'semilavorato_id'  => $semi->id,
                'quantita_kg'      => $qty,
            ]],
        ]);

        // Consume 20 of 30 -> OK.
        $this->controller->store($internalPayload('DOWN1', 20));
        $this->assertDatabaseHas('produzioni', ['lotto_produzione' => 'DOWN1']);

        // Consume 15 more (only 10 remain) -> rejected.
        try {
            $this->controller->store($internalPayload('DOWN2', 15));
            $this->fail('Expected ValidationException for semilavorato over-draw');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('materie_prime', $e->errors());
        }
        $this->assertDatabaseMissing('produzioni', ['lotto_produzione' => 'DOWN2']);
    }

    public function test_second_semilavorato_on_same_production_is_blocked(): void
    {
        $this->controller->store($this->purchasePayload('BASE', 60));
        $base = Produzione::where('lotto_produzione', 'BASE')->first();

        $this->controller->storeSemilavorato(
            Request::create('/x', 'POST', ['lotto' => 'SEMI1', 'nome_prodotto' => 'Semi', 'quantita_kg' => 30]),
            $base
        );
        // Second attempt returns a redirect-with-errors (no new row), not an exception.
        $this->controller->storeSemilavorato(
            Request::create('/x', 'POST', ['lotto' => 'SEMI2', 'nome_prodotto' => 'X', 'quantita_kg' => 5]),
            $base
        );

        $this->assertDatabaseMissing('lotti_semilavorati', ['lotto' => 'SEMI2']);
        $this->assertSame(1, LottoSemilavorato::where('produzione_id', $base->id)->count());
    }
}
