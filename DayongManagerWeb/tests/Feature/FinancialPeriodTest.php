<?php

namespace Tests\Feature;

use App\Filament\Pages\CollectionStatistics;
use App\Filament\Resources\Disbursements\DisbursementResource;
use App\Filament\Resources\Disbursements\Pages\ListDisbursements;
use App\Models\CollectionCycle;
use App\Models\Disbursement;
use App\Models\Member;
use App\Models\User;
use App\Services\DayongFinancialPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FinancialPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_claim_closes_period_and_carries_balance_into_the_next_report(): void
    {
        $this->travelTo(now()->setDate(2026, 1, 31));
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $cycle = CollectionCycle::create(['name' => 'First cycle', 'type' => 'Dayong', 'expected_amount' => 100]);
        $member = Member::create(['first_name' => 'Paid', 'last_name' => 'Member', 'council' => 'A']);
        $member->payments()->create(['collection_cycle_id' => $cycle->id, 'amount' => 100, 'date_paid' => '2026-01-10']);
        $claim = Disbursement::create(['disbursement_date' => '2026-01-31', 'category' => 'Claims', 'payee' => 'Claimant', 'amount' => 40]);

        Livewire::test(ListDisbursements::class)
            ->callTableAction('closeFinancialPeriod', $claim)
            ->assertHasNoTableActionErrors();
        $claim->refresh();
        $this->assertTrue($claim->closes_financial_period);
        $this->assertEquals(2060, $claim->closing_balance);
        $this->assertNotNull($claim->period_closed_at);
        $this->assertFalse(DisbursementResource::canEdit($claim));
        $this->assertFalse(DisbursementResource::canDelete($claim));

        $second = CollectionCycle::create(['name' => 'Second cycle', 'type' => 'Dayong', 'expected_amount' => 50]);
        $member->payments()->create(['collection_cycle_id' => $second->id, 'amount' => 50, 'date_paid' => '2026-02-02']);
        Disbursement::create(['disbursement_date' => '2026-02-03', 'category' => 'Meeting', 'payee' => 'Venue', 'amount' => 10]);
        $position = app(DayongFinancialPeriod::class)->position();
        $this->assertSame(206000, app(DayongFinancialPeriod::class)->balanceOn(\Illuminate\Support\Carbon::parse('2026-01-31')));
        $this->assertSame(211000, app(DayongFinancialPeriod::class)->balanceOn(\Illuminate\Support\Carbon::parse('2026-02-02')));
        $this->assertSame(210000, app(DayongFinancialPeriod::class)->balanceOn(\Illuminate\Support\Carbon::parse('2026-02-03')));
        $this->assertSame('2026-02-01', $position['periodStart']->toDateString());
        $this->assertSame(206000, $position['beginningBalanceCents']);
        $this->assertSame(5000, $position['collectionCents']);
        $this->assertSame(1000, $position['disbursementCents']);
        $this->assertSame(210000, $position['currentDayongBalanceCents']);
        $this->assertSame(['Meeting'], $position['ledger']->pluck('category')->all());

        $page = Livewire::test(CollectionStatistics::class)
            ->assertSee('Reporting period: February 1, 2026 to present')
            ->assertSee('Closing balance carried forward from January 31, 2026')
            ->callAction('downloadReport')
            ->assertHasNoActionErrors()
            ->assertFileDownloaded('collection-statistics-'.now()->format('Y-m-d').'.pdf', contentType: 'application/pdf');
        $pdf = base64_decode($page->effects['download']['content']);
        $this->assertMatchesRegularExpression('/\/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+612(?:\.0+)?\s+936(?:\.0+)?\s*\]/', $pdf);
        $template = file_get_contents(resource_path('views/reports/collection-statistics-pdf.blade.php'));
        $this->assertStringContainsString('font-family: Arial, Helvetica, sans-serif', $template);
        $this->assertStringContainsString('font-size: 12pt', $template);
    }

    public function test_only_claims_after_the_latest_closure_can_close_a_period(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $service = app(DayongFinancialPeriod::class);
        $meeting = Disbursement::create(['disbursement_date' => '2026-01-01', 'category' => 'Meeting', 'amount' => 10]);
        $first = Disbursement::create(['disbursement_date' => '2026-01-02', 'category' => 'Claims', 'amount' => 20]);
        $older = Disbursement::create(['disbursement_date' => '2026-01-01', 'category' => 'Claims', 'amount' => 20]);
        $this->assertFalse($service->canClose($meeting));
        $this->assertTrue($service->canClose($first));
        $service->close($first);
        $this->assertFalse($service->canClose($first->fresh()));
        $this->assertFalse($service->canClose($older));
    }
}
