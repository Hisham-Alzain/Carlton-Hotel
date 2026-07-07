<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SuperAdminBypassTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_passes_any_gate(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Gate::define('test.permission', fn ($u) => false);
        $this->assertTrue($admin->can('test.permission'));
    }

    public function test_staff_without_permission_is_denied(): void
    {
        $staff = User::factory()->staff()->create();
        Gate::define('test.permission', fn ($u) => false);
        $this->assertFalse($staff->can('test.permission'));
    }
}
