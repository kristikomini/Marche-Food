<?php

namespace Tests\Feature;

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
use Tests\TestCase;

/**
 * HTTP-level feature tests for the production workflow (Epic 3): balance
 * enforcement and semilavorati, exercised through the real routes/middleware.
 */
class ProduzioneHttpTest extends TestCase
{
    use RefreshDatabase;

    private MateriaPrima $mp;
    private SchedaProduzione $scheda;
    private AcquistoRiga $riga;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());

        $fornitore = Fornitore::create(['ragione_sociale' => 'Pesca', 'tipo' => 'alimentare']);
        $prodotto  = Prodotto::create(['codice_prodotto' => 'P1', 'nome' => 'Tonno', 'attivo' => true]);
        $this->mp  = MateriaPrima::create(['nome' => 'Tonno fresco']);
        $acq = Acquisto::create(['fornitore_id' => $fornitore->id, 'numero_documento' => 'D1', 'data_documento' => '2026-06-01', 'tipo_documento' => 'DDT']);
        $this->riga = AcquistoRiga::create(['acquisto_id' => $acq->id, 'nome_prodotto' => 'Tonno fresco', 'quantita_kg' => 100, 'lotto' => 'L100', 'data_in' => '2026-06-01']);
        $this->scheda = SchedaProduzione::create(['prodotto_id' => $prodotto->id, 'modello' => 'M1', 'revisione' => 0, 'data_revisione' => '2026-06-01', 'attiva' => true]);
        $this->scheda->ricette()->create(['materia_prima_id' => $this->mp->id, 'ordine' => 1]);
    }

    private function payload(string $lotto, float $qty): array
    {
        return [
            'scheda_id' => $this->scheda->id,
            'lotto_produzione' => $lotto,
            'data_produzione' => '2026-06-10',
            'materie_prime' => [[
                'materia_prima_id' => $this->mp->id,
                'source_type' => 'acquisto',
                'acquisto_riga_id' => $this->riga->id,
                'quantita_kg' => $qty,
            ]],
        ];
    }

    public function test_production_within_balance_succeeds(): void
    {
        $this->post('/produzioni', $this->payload('LP1', 60))
            ->assertRedirect('/produzioni');

        $this->assertDatabaseHas('produzioni', ['lotto_produzione' => 'LP1']);
    }

    public function test_overdraw_is_rejected_and_not_persisted(): void
    {
        $this->post('/produzioni', $this->payload('LP1', 60))->assertRedirect('/produzioni');

        $this->post('/produzioni', $this->payload('LP2', 50)) // only 40 remain
            ->assertSessionHasErrors('materie_prime');

        $this->assertDatabaseMissing('produzioni', ['lotto_produzione' => 'LP2']);
    }

    public function test_semilavorato_registration_and_consumption(): void
    {
        $this->post('/produzioni', $this->payload('BASE', 60))->assertRedirect('/produzioni');
        $base = Produzione::where('lotto_produzione', 'BASE')->firstOrFail();

        $this->post("/produzioni/{$base->id}/semilavorato", [
            'lotto' => 'SEMI1', 'nome_prodotto' => 'Semi', 'quantita_kg' => 30,
        ]);
        $semi = LottoSemilavorato::where('lotto', 'SEMI1')->firstOrFail();

        // Consume 20 of 30 → ok
        $this->post('/produzioni', [
            'scheda_id' => $this->scheda->id, 'lotto_produzione' => 'DOWN1', 'data_produzione' => '2026-06-11',
            'materie_prime' => [['materia_prima_id' => $this->mp->id, 'source_type' => 'interno', 'semilavorato_id' => $semi->id, 'quantita_kg' => 20]],
        ])->assertRedirect('/produzioni');
        $this->assertDatabaseHas('produzioni', ['lotto_produzione' => 'DOWN1']);

        // Consume 15 more (only 10 left) → rejected
        $this->post('/produzioni', [
            'scheda_id' => $this->scheda->id, 'lotto_produzione' => 'DOWN2', 'data_produzione' => '2026-06-11',
            'materie_prime' => [['materia_prima_id' => $this->mp->id, 'source_type' => 'interno', 'semilavorato_id' => $semi->id, 'quantita_kg' => 15]],
        ])->assertSessionHasErrors('materie_prime');
        $this->assertDatabaseMissing('produzioni', ['lotto_produzione' => 'DOWN2']);
    }

    public function test_operator_cannot_delete_but_admin_can(): void
    {
        $this->post('/produzioni', $this->payload('LP1', 10))->assertRedirect('/produzioni');
        $prod = Produzione::where('lotto_produzione', 'LP1')->firstOrFail();

        $operator = User::factory()->operator()->create();
        $this->actingAs($operator)->delete("/produzioni/{$prod->id}")->assertRedirect('/');
        $this->assertDatabaseHas('produzioni', ['id' => $prod->id]);

        $this->actingAs(User::factory()->admin()->create())->delete("/produzioni/{$prod->id}")->assertRedirect('/produzioni');
        $this->assertDatabaseMissing('produzioni', ['id' => $prod->id]);
    }
}
