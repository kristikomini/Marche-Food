<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_login_page_is_accessible_to_guests(): void
    {
        $this->get('/login')->assertStatus(200);
    }

    public function test_authenticated_user_is_redirected_away_from_login(): void
    {
        $user = User::factory()->operator()->create();
        $this->actingAs($user)->get('/login')->assertRedirect('/');
    }

    public function test_login_fails_with_wrong_credentials(): void
    {
        User::factory()->operator()->create(['email' => 'test@example.com']);

        $this->post('/login', [
            'email'    => 'test@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    public function test_login_succeeds_with_correct_credentials(): void
    {
        User::factory()->operator()->create([
            'email'    => 'op@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $this->post('/login', [
            'email'    => 'op@example.com',
            'password' => 'secret123',
        ])->assertRedirect('/');

        $this->assertAuthenticated();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->operator()->create();

        $this->actingAs($user)
             ->post('/logout')
             ->assertRedirect('/login');

        $this->assertGuest();
    }
}
