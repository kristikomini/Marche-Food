<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_loads_for_operator(): void
    {
        $user = User::factory()->operator()->create();

        $this->actingAs($user)->get('/')->assertStatus(200);
    }

    public function test_dashboard_loads_for_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/')->assertStatus(200);
    }

    public function test_health_endpoint_is_publicly_accessible(): void
    {
        $this->get('/up')->assertStatus(200);
    }
}
