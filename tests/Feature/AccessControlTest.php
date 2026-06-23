<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_is_redirected_to_login(): void
    {
        $this->get('/acquisti')->assertRedirect('/login');
        $this->get('/produzioni')->assertRedirect('/login');
        $this->get('/utenti')->assertRedirect('/login');
    }

    public function test_operator_is_redirected_away_from_admin_only_routes(): void
    {
        $operator = User::factory()->operator()->create();

        // POST /fornitori is admin-only; operator must be redirected to /
        $this->actingAs($operator)
             ->post('/fornitori', [])
             ->assertRedirect('/');
    }

    public function test_operator_cannot_delete_admin_records(): void
    {
        $operator = User::factory()->operator()->create();

        // DELETE on a non-existent id: middleware check runs before model lookup
        $this->actingAs($operator)
             ->delete('/fornitori/999')
             ->assertRedirect('/');
    }

    public function test_operator_can_access_shared_read_routes(): void
    {
        $operator = User::factory()->operator()->create();

        $this->actingAs($operator)->get('/acquisti')->assertStatus(200);
        $this->actingAs($operator)->get('/produzioni')->assertStatus(200);
        $this->actingAs($operator)->get('/fornitori')->assertStatus(200);
    }

    public function test_admin_can_access_user_management(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/utenti')->assertStatus(200);
    }

    public function test_operator_cannot_access_user_management(): void
    {
        $operator = User::factory()->operator()->create();

        $this->actingAs($operator)->get('/utenti')->assertRedirect('/');
    }
}
