<?php

namespace Tests\Feature;

use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Policies\RolePolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_roles_control_existing_resource_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $officer = User::factory()->create(['active' => true]);
        $officer->assignRole('membership_officer');

        $this->actingAs($officer);

        $this->assertTrue(MemberResource::canViewAny());
        $this->assertTrue(MemberResource::canCreate());
        $this->assertFalse($officer->hasPermission('ledger.manage'));
        $this->assertFalse(UserResource::canAccess());
    }

    public function test_existing_administrator_becomes_super_admin_and_can_manage_roles(): void
    {
        $admin = User::factory()->create(['active' => true, 'is_admin' => true]);
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin->refresh();

        $this->assertTrue($admin->hasRole('super_admin'));
        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', Role::class));

        $this->actingAs($admin);
        $this->assertTrue(UserResource::canAccess());
    }
}
