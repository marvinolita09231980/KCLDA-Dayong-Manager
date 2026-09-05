<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\CollectionCycle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(['username' => 'admin'], [
            'name' => 'System Administrator',
            'password' => 'Dayong@2026',
            'active' => true,
            'is_admin' => true,
        ]);
        CollectionCycle::firstOrCreate(['name' => 'CY 2026 Registration Fee'], ['type'=>'Registration Fee','expected_amount'=>100,'due_date'=>'2026-12-31','active'=>true]);
        CollectionCycle::firstOrCreate(['name' => 'CY 2026 Annual Dues (Optional)'], ['type'=>'Annual Dues','expected_amount'=>100,'due_date'=>'2026-12-31','active'=>true]);
    }
}

