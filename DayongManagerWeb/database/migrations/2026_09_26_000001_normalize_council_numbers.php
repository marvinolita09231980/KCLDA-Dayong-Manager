<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            foreach (DB::table('members')->select('id', 'council')->get() as $member) {
                if (preg_match('/^\s*(\d+)\s*-?\s*[a-z]*\s*$/i', $member->council, $matches)
                    && $matches[1] !== $member->council) {
                    DB::table('members')->where('id', $member->id)->update(['council' => $matches[1]]);
                }
            }
        });
    }

    public function down(): void
    {
        // Original suffixes can only be recovered from a database backup.
    }
};
