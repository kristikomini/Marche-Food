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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 — soft-delete + restore. Covers the Cestino restore/force-delete
 * flow, the delete guards that preserve the "can't remove referenced data"
 * invariant, admin-only access, and the balance-release correctness of the
 * raw-query soft-delete filters.
 */
class CestinoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Acquisto $acquisto;
    private AcquistoRiga $riga;
    private SchedaProduzione $scheda;
    private MateriaPrima $mp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();

        $fornitore = Fornitore::create(['ragione_sociale' => 'Pesca', 'tipo' => 'alimentare']);
        $prodotto  = Prodotto::create(['nome' => 'Tonno', 'attivo' => true]);
        $this->mp  = MateriaPrima::create(['nome' => 'Tonno fresco']);
        $this->acquisto = Acquisto::create(['fornitore_id' => $fornitore->id, 'numero_documento' => 'D1', 'data_documento' => '2026-06-01', 'tipo_documento' => 'DDT']);
        $this->riga = AcquistoRiga::create(['acquisto_id' => $this->acquisto->id, 'nome_prodotto' => 'Tonno fresco', 'quantita_kg' => 100, 'lotto' => 'L100', 'data_in' => '2026-06-01']);
        $this->scheda = SchedaProduzione::create(['prodotto_id' => $prodotto->id, 'modello' => 'M1', 'revisione' => 0, 'data_revisione' => '2026-06-01', 'attiva' => true]);
        $this->scheda->ricette()->create(['materia_prima_id' => $this->mp->id, 'ordine' => 1]);
    }

    private function produci(string $lotto, float $qty): Produzione
    {
        $this->actingAs($this->admin)->post('/produzioni', [
            'scheda_id'        => $this->scheda->id,
            'lotto_produzione' => $lotto,
            'data_produzione'  => '2026-06-10',
            'materie_prime'    => [[
                'materia_prima_id' => $this->mp->id,
                'source_type'      => 'acquisto',
                'acquisto_riga_id' => $this->riga->id,
                'quantita_kg'      => $qty,
            ]],
        ])->assertRedirect('/produzioni');

        return Produzione::where('lotto_produzione', $lotto)->firstOrFail();
    }

    public function test_deleted_acquisto_can_be_restored_then_force_deleted(): void
    {
        $this->actingAs($this->admin)->delete("/acquisti/{$this->acquisto->id}")->assertRedirect('/acquisti');
        $this->assertSoftDeleted('acquisti', ['id' => $this->acquisto->id]);

        $this->actingAs($this->admin)->get('/cestino')->assertOk();

        $this->actingAs($this->admin)->post("/cestino/acquisti/{$this->acquisto->id}/restore")->assertRedirect();
        $this->assertNotSoftDeleted('acquisti', ['id' => $this->acquisto->id]);

        // Trash again, then permanently remove
        $this->actingAs($this->admin)->delete("/acquisti/{$this->acquisto->id}");
        $this->actingAs($this->admin)->delete("/cestino/acquisti/{$this->acquisto->id}")->assertRedirect();
        $this->assertDatabaseMissing('acquisti', ['id' => $this->acquisto->id]);
    }

    public function test_cannot_delete_acquisto_with_lot_consumed_by_active_production(): void
    {
        $this->produci('LP1', 60);

        $this->actingAs($this->admin)->delete("/acquisti/{$this->acquisto->id}")
            ->assertSessionHas('error');
        $this->assertNotSoftDeleted('acquisti', ['id' => $this->acquisto->id]);
    }

    public function test_deleting_production_releases_consumed_lot_balance(): void
    {
        $prod = $this->produci('LP1', 100); // consume the whole lot

        // Fully consumed → a second production overdraws and is rejected
        $this->actingAs($this->admin)->post('/produzioni', [
            'scheda_id' => $this->scheda->id, 'lotto_produzione' => 'LP2', 'data_produzione' => '2026-06-11',
            'materie_prime' => [['materia_prima_id' => $this->mp->id, 'source_type' => 'acquisto', 'acquisto_riga_id' => $this->riga->id, 'quantita_kg' => 50]],
        ])->assertSessionHasErrors('materie_prime');

        // Trash the first production → its consumption must be released
        $this->actingAs($this->admin)->delete("/produzioni/{$prod->id}")->assertRedirect('/produzioni');
        $this->assertSoftDeleted('produzioni', ['id' => $prod->id]);

        // The lot is available again
        $this->actingAs($this->admin)->post('/produzioni', [
            'scheda_id' => $this->scheda->id, 'lotto_produzione' => 'LP3', 'data_produzione' => '2026-06-12',
            'materie_prime' => [['materia_prima_id' => $this->mp->id, 'source_type' => 'acquisto', 'acquisto_riga_id' => $this->riga->id, 'quantita_kg' => 90]],
        ])->assertRedirect('/produzioni');
        $this->assertDatabaseHas('produzioni', ['lotto_produzione' => 'LP3']);
    }

    public function test_cestino_is_admin_only(): void
    {
        $operator = User::factory()->operator()->create();
        $this->actingAs($operator)->get('/cestino')->assertRedirect('/');
    }
}
