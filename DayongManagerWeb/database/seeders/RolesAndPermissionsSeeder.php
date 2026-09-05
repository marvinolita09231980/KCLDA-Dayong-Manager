<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    private const PERMISSIONS = [
        'members.view',
        'members.manage',
        'collections.view',
        'collections.manage',
        'compliance.view',
        'ledger.view',
        'ledger.manage',
        'disbursements.view',
        'disbursements.manage',
    ];

    private const ROLES = [
        'membership_officer' => [
            'members.view',
            'members.manage',
            'collections.view',
            'compliance.view',
        ],
        'treasurer' => [
            'members.view',
            'collections.view',
            'collections.manage',
            'ledger.view',
            'ledger.manage',
            'disbursements.view',
            'disbursements.manage',
        ],
        'viewer' => [
            'members.view',
            'collections.view',
            'compliance.view',
            'ledger.view',
            'disbursements.view',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate('super_admin', 'web');

        foreach (self::ROLES as $name => $permissions) {
            Role::findOrCreate($name, 'web')->syncPermissions($permissions);
        }

        User::query()->where('is_admin', true)->each(
            fn (User $user) => $user->assignRole('super_admin')
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
