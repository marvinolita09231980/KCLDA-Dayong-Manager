<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Member;
use App\Filament\Resources\Members\MemberResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GranularPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_with_super_admin_role_respects_revoked_edit_permission(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $role = \Spatie\Permission\Models\Role::findByName('super_admin', 'web');
        $user = User::factory()->create(['active' => true, 'is_admin' => true, 'legacy_permissions' => ['members.edit']]);
        $user->assignRole($role);
        $user->givePermissionTo('members.edit');
        $this->actingAs($user);
        $member = Member::create(['first_name' => 'Ana', 'last_name' => 'Cruz', 'council' => 'A']);
        $this->assertTrue(MemberResource::canEdit($member));
        $role->revokePermissionTo('members.edit');
        $this->assertFalse(MemberResource::canEdit($member));
        $this->get('/admin/members/'.$member->id.'/edit')->assertForbidden();
        $this->assertTrue(\App\Filament\Resources\Users\UserResource::canAccess());
        $this->assertTrue((new \App\Policies\RolePolicy)->before($user));
    }

    public function test_view_edit_and_delete_are_independent(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['active' => true, 'is_admin' => false]);
        $user->givePermissionTo('members.view');
        $this->actingAs($user);
        $member = Member::create(['first_name' => 'Ana', 'last_name' => 'Cruz', 'council' => 'A']);
        $this->assertTrue(MemberResource::canViewAny());
        $this->assertFalse(MemberResource::canEdit($member));
        $this->assertFalse(MemberResource::canDeleteAny());
        $this->get('/admin/members/'.$member->id.'/edit')->assertForbidden();
        $user->givePermissionTo('members.edit');
        $this->assertTrue(MemberResource::canEdit($member));
        $this->assertFalse(MemberResource::canDelete($member));
        $this->assertFalse(MemberResource::canCreate());
        $user->revokePermissionTo('members.edit');
        $user->givePermissionTo('members.delete');
        $this->assertTrue(MemberResource::canDelete($member));
        $this->assertTrue(MemberResource::canDeleteAny());
        $this->assertFalse(MemberResource::canEdit($member));
    }
}
