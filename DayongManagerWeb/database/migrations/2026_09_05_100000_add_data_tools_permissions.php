<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['tools.import', 'tools.export', 'tools.backup'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', ['tools.import', 'tools.export', 'tools.backup'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
