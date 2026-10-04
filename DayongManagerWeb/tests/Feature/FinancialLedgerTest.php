<?php

namespace Tests\Feature;

use App\Filament\Pages\FinancialLedger;
use App\Models\BankTransaction;
use App\Models\CollectionCycle;
use App\Models\Disbursement;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FinancialLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_ledger_combines_records_in_date_order_and_filters_inclusively(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));

        $member = Member::create(['first_name' => 'Ana', 'last_name' => 'Santos', 'council' => 'North']);
        $cycle = CollectionCycle::create(['name' => 'September', 'type' => 'Dayong', 'expected_amount' => 100, 'active' => true]);
        Payment::create(['member_id' => $member->id, 'collection_cycle_id' => $cycle->id, 'amount' => 100, 'date_paid' => '2026-09-01', 'receipt_number' => 'PAY-1']);
        Disbursement::create(['disbursement_date' => '2026-09-02', 'amount' => 50, 'payee' => 'Supplier', 'voucher_number' => 'DIS-1']);
        BankTransaction::create(['transaction_date' => '2026-09-03', 'transaction_type' => 'Deposit', 'amount' => 75, 'reference_number' => 'BANK-1']);

        $ledger = Livewire::test(FinancialLedger::class)
            ->assertSeeInOrder(['BANK-1', 'DIS-1', 'PAY-1']);

        $ledger->set('dateFrom', '2026-09-02')
            ->set('dateTo', '2026-09-03')
            ->assertSee('BANK-1')
            ->assertSee('DIS-1')
            ->assertDontSee('PAY-1');

        $ledger->set('dateTo', '2026-09-02')
            ->assertSee('DIS-1')
            ->assertDontSee('BANK-1');
    }

    public function test_ledger_pagination_shows_page_numbers_and_changes_records(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));

        for ($day = 1; $day <= 26; $day++) {
            Disbursement::create([
                'disbursement_date' => sprintf('2026-09-%02d', $day),
                'amount' => 10,
                'payee' => 'Payee '.$day,
                'voucher_number' => sprintf('VOUCHER-%03d', $day),
            ]);
        }

        Livewire::test(FinancialLedger::class)
            ->assertSee('Showing 1–25 of 26')
            ->assertSee('VOUCHER-026')
            ->assertDontSee('VOUCHER-001')
            ->call('nextPage')
            ->assertSee('Showing 26–26 of 26')
            ->assertSee('VOUCHER-001')
            ->assertDontSee('VOUCHER-026');
    }

    public function test_payment_without_entered_date_is_not_shown_as_creation_date(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));

        $member = Member::create(['first_name' => 'Ana', 'last_name' => 'Santos', 'council' => 'North']);
        $cycle = CollectionCycle::create(['name' => 'September', 'type' => 'Dayong', 'expected_amount' => 100, 'active' => true]);
        Payment::create(['member_id' => $member->id, 'collection_cycle_id' => $cycle->id, 'amount' => 100, 'date_paid' => null, 'receipt_number' => 'UNDATED-1']);

        Livewire::test(FinancialLedger::class)
            ->assertSee('UNDATED-1')
            ->assertSee('No date entered')
            ->set('dateFrom', '2026-09-01')
            ->assertDontSee('UNDATED-1');
    }
}
