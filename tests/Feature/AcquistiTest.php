<?php

namespace Tests\Feature;

use App\Models\Acquisto;
use App\Models\Fornitore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcquistiTest extends TestCase
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

    public function test_acquisti_index_is_accessible_to_operator(): void
    {
        $this->actingAs($this->operator)->get('/acquisti')->assertStatus(200);
    }

    public function test_acquisti_create_form_is_accessible_to_operator(): void
    {
        $this->actingAs($this->operator)->get('/acquisti/create')->assertStatus(200);
    }

    public function test_acquisti_export_returns_csv(): void
    {
        $response = $this->actingAs($this->operator)->get('/acquisti/export');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_operator_cannot_delete_acquisto(): void
    {
        $fornitore = Fornitore::create([
            'ragione_sociale' => 'Test Fornitore',
            'codice'          => 'F001',
            'tipo'            => 'alimentare',
            'attivo'          => true,
        ]);

        $acquisto = Acquisto::create([
            'fornitore_id'     => $fornitore->id,
            'numero_documento' => 'DDT-001',
            'data_documento'   => now()->toDateString(),
            'tipo_documento'   => 'DDT',
        ]);

        $this->actingAs($this->operator)
             ->delete("/acquisti/{$acquisto->id}")
             ->assertRedirect('/');

        $this->assertDatabaseHas('acquisti', ['id' => $acquisto->id]);
    }

    public function test_admin_can_delete_acquisto(): void
    {
        $fornitore = Fornitore::create([
            'ragione_sociale' => 'Test Fornitore',
            'codice'          => 'F002',
            'tipo'            => 'alimentare',
            'attivo'          => true,
        ]);

        $acquisto = Acquisto::create([
            'fornitore_id'     => $fornitore->id,
            'numero_documento' => 'DDT-002',
            'data_documento'   => now()->toDateString(),
            'tipo_documento'   => 'DDT',
        ]);

        $this->actingAs($this->admin)
             ->delete("/acquisti/{$acquisto->id}")
             ->assertRedirect('/acquisti');

        $this->assertSoftDeleted('acquisti', ['id' => $acquisto->id]);
    }
}
