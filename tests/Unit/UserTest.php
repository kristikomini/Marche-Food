<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function test_admin_role_is_detected_correctly(): void
    {
        $user = new User(['role' => 'admin']);
        $this->assertTrue($user->isAdmin());
        $this->assertFalse($user->isOperator());
    }

    public function test_operator_role_is_detected_correctly(): void
    {
        $user = new User(['role' => 'operator']);
        $this->assertTrue($user->isOperator());
        $this->assertFalse($user->isAdmin());
    }

    public function test_unknown_role_is_neither_admin_nor_operator(): void
    {
        $user = new User(['role' => null]);
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isOperator());
    }
}
