<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique();
            $table->string('email')->nullable()->change();
        });

        foreach (DB::table('users')->select('id', 'email')->orderBy('id')->get() as $user) {
            DB::table('users')->where('id', $user->id)->update([
                'username' => $user->email === 'admin@kclda.local' ? 'admin' : 'user'.$user->id,
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }
};
