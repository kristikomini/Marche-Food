<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Recall;
use App\Models\User;
use App\Models\Vendita;
use App\Models\VenditaRiga;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 — recall workflow (P-B6). Opening a recall on a delivered lot must
 * auto-create per-customer notification tasks and drive the stateful workflow.
 */
class RecallTest extends TestCase
{
    use RefreshDatabase;

    private function sellLot(string $lotto): Cliente
    {
        $cliente = Cliente::create(['ragione_sociale' => 'Ristorante A', 'codice_cliente' => 'C1', 'attivo' => true]);
        $vendita = Vendita::create(['cliente_id' => $cliente->id, 'numero_documento' => 'V1', 'data_documento' => '2026-06-01', 'tipo_documento' => 'DDT']);
        VenditaRiga::create(['vendita_id' => $vendita->id, 'nome_prodotto' => 'Tonno', 'quantita_kg' => 5, 'lotto' => $lotto]);

        return $cliente;
    }

    public function test_opening_a_recall_creates_customer_notifications(): void
    {
        $admin = User::factory()->admin()->create();
        $cliente = $this->sellLot('LOT-1');

        $this->actingAs($admin)->post('/recall', [
            'lotto' => 'LOT-1', 'prodotto' => 'Tonno', 'motivo' => 'Contaminazione microbiologica',
        ])->assertRedirect();

        $recall = Recall::where('lotto', 'LOT-1')->firstOrFail();
        $this->assertEquals('aperto', $recall->stato);
        $this->assertDatabaseHas('recall_notifiche', [
            'recall_id'  => $recall->id,
            'cliente_id' => $cliente->id,
            'notificato' => false,
        ]);
    }

    public function test_marking_a_notification_advances_state_to_in_corso(): void
    {
        $admin = User::factory()->admin()->create();
        $this->sellLot('LOT-2');
        $this->actingAs($admin)->post('/recall', ['lotto' => 'LOT-2', 'motivo' => 'X']);
        $recall = Recall::where('lotto', 'LOT-2')->firstOrFail();
        $notifica = $recall->notifiche()->firstOrFail();

        $this->actingAs($admin)->post("/recall/{$recall->id}/notifiche/{$notifica->id}", ['notificato' => true])
            ->assertRedirect();

        $this->assertEquals('in_corso', $recall->fresh()->stato);
        $this->assertDatabaseHas('recall_notifiche', ['id' => $notifica->id, 'notificato' => true]);
    }

    public function test_closing_a_recall_records_a_closure_date(): void
    {
        $admin = User::factory()->admin()->create();
        $this->sellLot('LOT-3');
        $this->actingAs($admin)->post('/recall', ['lotto' => 'LOT-3', 'motivo' => 'X']);
        $recall = Recall::where('lotto', 'LOT-3')->firstOrFail();

        $this->actingAs($admin)->put("/recall/{$recall->id}/stato", ['stato' => 'chiuso'])->assertRedirect();

        $fresh = $recall->fresh();
        $this->assertEquals('chiuso', $fresh->stato);
        $this->assertNotNull($fresh->data_chiusura);
    }

    public function test_recall_requires_authentication(): void
    {
        $this->get('/recall')->assertRedirect('/login');
    }
}
