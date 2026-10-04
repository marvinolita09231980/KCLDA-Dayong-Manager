<?php

namespace Tests\Feature;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CouncilNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_saves_store_council_numbers_without_suffixes(): void
    {
        $member = Member::create(['first_name' => 'Test', 'last_name' => 'Member', 'council' => '8814 -CKC']);
        $this->assertDatabaseHas('members', ['id' => $member->id, 'council' => '8814']);
        $member->update(['council' => '17822-OLFP']);
        $this->assertDatabaseHas('members', ['id' => $member->id, 'council' => '17822']);
    }

    public function test_migration_cleans_existing_database_values(): void
    {
        foreach (['8814 CKC', '17822-OLFP', '8814 -CKC'] as $index => $council) {
            DB::table('members')->insert(['first_name' => 'Test'.$index, 'last_name' => 'Member', 'council' => $council]);
        }
        $migration = require database_path('migrations/2026_09_26_000001_normalize_council_numbers.php');
        $migration->up();
        $this->assertSame(['8814', '17822', '8814'], DB::table('members')->orderBy('id')->pluck('council')->all());
    }
}
