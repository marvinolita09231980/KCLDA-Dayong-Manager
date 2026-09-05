<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            foreach (['members', 'collections', 'ledger', 'disbursements'] as $domain) {
                $new = collect(['create', 'edit', 'delete'])->map(fn ($action) => Permission::findOrCreate($domain.'.'.$action, 'web'));
                $old = Permission::where('name', $domain.'.manage')->where('guard_name', 'web')->first();
                if ($old) {
                    foreach (['role_has_permissions', 'model_has_permissions'] as $table) {
                        foreach (DB::table($table)->where('permission_id', $old->id)->get() as $assignment) {
                            foreach ($new as $permission) {
                                $copy = (array) $assignment;
                                $copy['permission_id'] = $permission->id;
                                DB::table($table)->insertOrIgnore($copy);
                            }
                        }
                    }
                    $old->delete();
                }
            }
            \App\Models\User::whereNotNull('legacy_permissions')->each(function ($user) {
                $user->legacy_permissions = collect($user->legacy_permissions)->flatMap(function ($permission) {
                    if (preg_match('/^(members|collections|ledger|disbursements)\.manage$/', $permission, $matches)) {
                        return array_map(fn ($action) => $matches[1].'.'.$action, ['create', 'edit', 'delete']);
                    }
                    return [$permission];
                })->unique()->values()->all();
                $user->save();
            });
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Keep granular assignments intact: merging them would broaden access.
    }
};
