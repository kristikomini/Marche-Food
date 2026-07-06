<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 4 — user management (admin only): creation, validation, password
 * reset, and the self-deletion guard.
 */
class UtenteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_user(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/utenti', [
            'name' => 'Mario Rossi', 'email' => 'mario@example.com',
            'password' => 'segreto123', 'password_confirmation' => 'segreto123',
            'role' => 'operator',
        ])->assertRedirect('/utenti');

        $this->assertDatabaseHas('users', ['email' => 'mario@example.com', 'role' => 'operator']);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'dup@example.com']);

        $this->actingAs($admin)->post('/utenti', [
            'name' => 'X', 'email' => 'dup@example.com',
            'password' => 'segreto123', 'password_confirmation' => 'segreto123',
            'role' => 'operator',
        ])->assertSessionHasErrors('email');
    }

    public function test_admin_can_reset_a_user_password(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->operator()->create();

        $this->actingAs($admin)->post("/utenti/{$target->id}/reset-password", [
            'password' => 'nuovaPassword1', 'password_confirmation' => 'nuovaPassword1',
        ])->assertRedirect('/utenti');

        $this->assertTrue(Hash::check('nuovaPassword1', $target->fresh()->password));
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->delete("/utenti/{$admin->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_operator_cannot_manage_users(): void
    {
        $operator = User::factory()->operator()->create();

        $this->actingAs($operator)->post('/utenti', [
            'name' => 'X', 'email' => 'x@example.com',
            'password' => 'segreto123', 'password_confirmation' => 'segreto123', 'role' => 'operator',
        ])->assertRedirect('/');
    }
}
