<?php

namespace Tests\Feature;

use App\Models\Produzione;
use App\Models\Prodotto;
use App\Models\SchedaProduzione;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProduzioniTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->operator = User::factory()->operator()->create();
        $this->admin    = User::factory()->admin()->create();
    }

    public function test_produzioni_index_is_accessible_to_operator(): void
    {
        $this->actingAs($this->operator)->get('/produzioni')->assertStatus(200);
    }

    public function test_produzioni_export_returns_csv(): void
    {
        $response = $this->actingAs($this->operator)->get('/produzioni/export');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_operator_cannot_delete_produzione(): void
    {
        $prodotto = Prodotto::create(['nome' => 'Prodotto Test', ]);
        $scheda   = SchedaProduzione::create([
            'prodotto_id'   => $prodotto->id,
            'modello'       => 'TEST',
            'revisione'     => 1,
            'data_revisione' => now()->toDateString(),
            'attiva'        => true,
        ]);

        $produzione = Produzione::create([
            'scheda_id'        => $scheda->id,
            'lotto_produzione' => 'LOT-2026-001',
            'data_produzione'  => now()->toDateString(),
        ]);

        $this->actingAs($this->operator)
             ->delete("/produzioni/{$produzione->id}")
             ->assertRedirect('/');

        $this->assertDatabaseHas('produzioni', ['id' => $produzione->id]);
    }

    public function test_admin_can_delete_produzione(): void
    {
        $prodotto = Prodotto::create(['nome' => 'Prodotto Test 2', ]);
        $scheda   = SchedaProduzione::create([
            'prodotto_id'    => $prodotto->id,
            'modello'        => 'TEST',
            'revisione'      => 1,
            'data_revisione' => now()->toDateString(),
            'attiva'         => true,
        ]);

        $produzione = Produzione::create([
            'scheda_id'        => $scheda->id,
            'lotto_produzione' => 'LOT-2026-002',
            'data_produzione'  => now()->toDateString(),
        ]);

        $this->actingAs($this->admin)
             ->delete("/produzioni/{$produzione->id}")
             ->assertRedirect('/produzioni');

        $this->assertSoftDeleted('produzioni', ['id' => $produzione->id]);
    }
}
