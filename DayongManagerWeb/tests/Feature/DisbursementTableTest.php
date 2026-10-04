<?php

namespace Tests\Feature;

use App\Filament\Resources\Disbursements\Pages\ListDisbursements;
use App\Models\Disbursement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DisbursementTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_total_disbursements_appears_below_the_table_and_follows_search(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        Disbursement::create(['disbursement_date' => '2026-01-01', 'payee' => 'Supplier A', 'amount' => 100.25]);
        Disbursement::create(['disbursement_date' => '2026-01-02', 'payee' => 'Supplier B', 'amount' => 50.50]);

        Livewire::test(ListDisbursements::class)
            ->assertSee('Total disbursements')
            ->assertTableColumnSummarySet('amount', 'total', 150.75)
            ->searchTable('Supplier A')
            ->assertTableColumnSummarySet('amount', 'total', 100.25);
    }
}
