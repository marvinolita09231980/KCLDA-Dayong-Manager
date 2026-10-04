<?php

namespace Tests\Feature;

use App\Filament\Pages\FinancialReport;
use App\Models\BankTransaction;
use App\Models\CollectionCycle;
use App\Models\Disbursement;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FinancialReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_totals_categories_and_inclusive_dates(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 27));
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $member = Member::create(['first_name' => 'Ana', 'last_name' => 'Cruz', 'council' => '1']);
        foreach (['2026-09-01', '2026-09-27', '2026-08-31', null] as $index => $date) {
            $cycle = CollectionCycle::create(['name' => 'Cycle '.$index, 'type' => 'Dayong', 'expected_amount' => 100]);
            Payment::create(['member_id' => $member->id, 'collection_cycle_id' => $cycle->id, 'amount' => '100.25', 'date_paid' => $date]);
        }
        foreach ([['Meeting', '2026-09-01', '30.10'], ['Meeting', '2026-09-27', '20.20'], ['', '2026-09-20', '10.05'], ['Old category', '2026-08-31', '5.00']] as [$category, $date, $amount]) {
            Disbursement::create(['category' => $category, 'disbursement_date' => $date, 'amount' => $amount]);
        }
        BankTransaction::create(['transaction_date' => '2026-09-01', 'transaction_type' => 'Deposit', 'amount' => 1000]);

        $this->get('/admin/financial-report')->assertOk()->assertSee('Financial Report');
        Livewire::test(FinancialReport::class)
            ->assertSet('dateFrom', '')->assertSet('dateTo', '')
            ->assertViewHas('collectionCents', 40100)
            ->assertViewHas('disbursementCents', 6535)
            ->assertViewHas('netCents', 33565)
            ->assertViewHas('undatedPayments', 1)
            ->assertSee('Others')->assertSee('50.30')->assertSee('Old category')
            ->set('dateFrom', '2026-09-01')->set('dateTo', '2026-09-27')
            ->assertViewHas('collectionCents', 20050)
            ->assertViewHas('disbursementCents', 6035)
            ->set('dateFrom', '2026-09-27')
            ->assertViewHas('collectionCents', 10025)
            ->assertViewHas('disbursementCents', 2020)
            ->call('clearDates')
            ->assertViewHas('collectionCents', 40100)
            ->assertViewHas('disbursementCents', 6535)
            ->assertSee('Old category')->assertSee('included in this all-dates report');
    }

    public function test_invalid_dates_hide_totals_and_empty_ranges_show_zero(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        Livewire::test(FinancialReport::class)
            ->call('clearDates')
            ->assertViewHas('netCents', 0)->assertSee('No records in the selected period.')
            ->set('dateFrom', '2026-09-30')->set('dateTo', '2026-09-01')
            ->assertSee('Date to must be on or after date from.')->assertDontSee('Total collections')
            ->set('dateFrom', '2026-02-30')
            ->assertViewHas('dateErrors', fn ($errors) => count($errors) > 0)
            ->assertDontSee('Total collections');
    }

    public function test_report_respects_each_financial_permission(): void
    {
        $user = User::factory()->create(['active' => true, 'is_admin' => false, 'legacy_permissions' => ['disbursements.view']]);
        $this->actingAs($user);
        Livewire::test(FinancialReport::class)
            ->assertSee('Total disbursements')->assertDontSee('Total collections')
            ->assertDontSee('Net collections less disbursements');

        $user->update(['legacy_permissions' => ['collections.view']]);
        Livewire::test(FinancialReport::class)
            ->assertSee('Total collections')->assertDontSee('Total disbursements')
            ->assertDontSee('Net collections less disbursements');

        $user->update(['legacy_permissions' => []]);
        $this->get('/admin/financial-report')->assertForbidden();
        $user->update(['active' => false, 'legacy_permissions' => ['collections.view']]);
        $this->assertFalse(FinancialReport::canAccess());
    }
}
