<?php

namespace Tests\Feature;

use App\Models\Fornitore;
use App\Models\LottoImballaggioPrimario;
use App\Models\Prodotto;
use App\Models\Produzione;
use App\Models\ProduzioneImballaggioPrimario;
use App\Models\SchedaProduzione;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 — packaging (imballaggi) delete guard. Exercises the Phase 1
 * soft-delete guard that blocks removing a packaging lot used by an active
 * production, and confirms it frees once the production is trashed.
 */
class ImballaggioTest extends TestCase
{
    use RefreshDatabase;

    private function lotto(): LottoImballaggioPrimario
    {
        $f = Fornitore::create(['ragione_sociale' => 'Imb SRL', 'tipo' => 'imballaggio_primario']);

        return LottoImballaggioPrimario::create([
            'fornitore_id' => $f->id, 'componente' => 'Vaschetta', 'data_in' => '2026-06-01',
        ]);
    }

    private function useInProduction(LottoImballaggioPrimario $lotto): Produzione
    {
        $prodotto = Prodotto::create(['nome' => 'X', 'attivo' => true]);
        $scheda   = SchedaProduzione::create(['prodotto_id' => $prodotto->id, 'modello' => 'M', 'revisione' => 0, 'data_revisione' => '2026-06-01', 'attiva' => true]);
        $prod     = Produzione::create(['scheda_id' => $scheda->id, 'lotto_produzione' => 'LP', 'data_produzione' => '2026-06-02']);
        ProduzioneImballaggioPrimario::create(['produzione_id' => $prod->id, 'lotto_imballaggio_id' => $lotto->id, 'quantita_usata' => 1]);

        return $prod;
    }

    public function test_admin_can_soft_delete_unused_imballaggio(): void
    {
        $admin = User::factory()->admin()->create();
        $lotto = $this->lotto();

        $this->actingAs($admin)->delete("/imballaggi/primari/{$lotto->id}")->assertRedirect();
        $this->assertSoftDeleted('lotti_imballaggi_primari', ['id' => $lotto->id]);
    }

    public function test_cannot_delete_imballaggio_used_in_active_production(): void
    {
        $admin = User::factory()->admin()->create();
        $lotto = $this->lotto();
        $this->useInProduction($lotto);

        $this->actingAs($admin)->delete("/imballaggi/primari/{$lotto->id}")->assertSessionHas('error');
        $this->assertNotSoftDeleted('lotti_imballaggi_primari', ['id' => $lotto->id]);
    }

    public function test_deleting_the_production_frees_the_imballaggio(): void
    {
        $admin = User::factory()->admin()->create();
        $lotto = $this->lotto();
        $prod  = $this->useInProduction($lotto);

        $prod->delete(); // trash the production → no longer an active reference

        $this->actingAs($admin)->delete("/imballaggi/primari/{$lotto->id}")->assertRedirect();
        $this->assertSoftDeleted('lotti_imballaggi_primari', ['id' => $lotto->id]);
    }
}
