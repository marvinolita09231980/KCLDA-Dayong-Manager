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
        'tools.import',
        'tools.export',
        'tools.backup',
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

        $expand = fn (array $permissions) => collect($permissions)->flatMap(fn ($permission) => str_ends_with($permission, '.manage')
            ? array_map(fn ($action) => str_replace('.manage', '.'.$action, $permission), ['create', 'edit', 'delete'])
            : [$permission])->all();

        foreach ($expand(self::PERMISSIONS) as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        if (! Role::where('name', 'super_admin')->where('guard_name', 'web')->exists()) {
            Role::create(['name' => 'super_admin', 'guard_name' => 'web'])->syncPermissions($expand(self::PERMISSIONS));
        }

        foreach (self::ROLES as $name => $permissions) {
            Role::findOrCreate($name, 'web')->syncPermissions($expand($permissions));
        }

        User::query()->where('is_admin', true)->each(
            fn (User $user) => $user->assignRole('super_admin')
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
