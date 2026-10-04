<?php

namespace Tests\Feature;

use App\Filament\Pages\CollectionStatistics;
use App\Models\CollectionCycle;
use App\Models\BankTransaction;
use App\Models\Disbursement;
use App\Models\Member;
use App\Models\Payable;
use App\Models\User;
use App\Services\CollectionStatisticsReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CollectionStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_history_is_loaded_in_bulk_outside_the_selected_cycle(): void
    {
        $registration = CollectionCycle::create(['name' => 'Registration 2025', 'type' => 'Registration Fee', 'expected_amount' => 100]);
        $annual = CollectionCycle::create(['name' => 'Annual 2026', 'type' => 'Annual Dues', 'expected_amount' => 100]);
        $createMember = function (int $index) use ($registration, $annual): void {
            $member = Member::create(['first_name' => 'Test '.$index, 'last_name' => 'Member', 'council' => 'A']);
            $member->payments()->create(['collection_cycle_id' => $registration->id, 'amount' => 100]);
            $member->payments()->create(['collection_cycle_id' => $annual->id, 'amount' => 100]);
        };
        $measure = function () use ($annual): array {
            $connection = \Illuminate\Support\Facades\DB::connection();
            $connection->flushQueryLog();
            $connection->enableQueryLog();
            try {
                $report = app(CollectionStatisticsReport::class)->generate([$annual->id]);

                return [$report, count($connection->getQueryLog())];
            } finally {
                $connection->disableQueryLog();
                $connection->flushQueryLog();
            }
        };

        $createMember(0);
        [$singleReport, $singleQueries] = $measure();
        for ($index = 1; $index <= 10; $index++) {
            $createMember($index);
        }
        [$report, $queries] = $measure();

        $this->assertSame(1, $singleReport['totals']['paid']);
        $this->assertSame(11, $report['totals']['paid']);
        $this->assertSame(110000, $report['totals']['amount_cents']);
        $this->assertSame($singleQueries, $queries, 'Report queries must not grow with the member count.');
    }

    public function test_financial_position_uses_all_recorded_money_and_respects_permissions(): void
    {
        $admin = User::factory()->create(['active' => true, 'is_admin' => true]);
        $this->actingAs($admin);
        $cycle = CollectionCycle::create(['name' => 'Cycle', 'type' => 'Dayong', 'expected_amount' => 200]);
        $member = Member::create(['first_name' => 'Paid', 'last_name' => 'Member', 'council' => 'A']);
        $member->payments()->create(['collection_cycle_id' => $cycle->id, 'amount' => 200]);
        BankTransaction::create(['transaction_date' => '2026-01-01', 'transaction_type' => 'Deposit', 'amount' => 1000]);
        BankTransaction::create(['transaction_date' => '2026-01-02', 'transaction_type' => 'Withdrawal', 'amount' => 300]);
        Disbursement::create(['disbursement_date' => '2026-01-03', 'category' => 'Claims', 'amount' => 30]);
        Disbursement::create(['disbursement_date' => '2026-01-04', 'category' => 'Meeting', 'amount' => 20]);

        Livewire::test(CollectionStatistics::class)
            ->assertViewHas('financialPosition', fn ($position) =>
                $position['closure'] === null && $position['periodStart'] === null
                && $position['beginningBalanceCents'] === 200000
                && $position['collectionCents'] === 20000
                && $position['totalFundsCents'] === 220000
                && $position['disbursementCents'] === 5000
                && $position['disbursementItems']->all() === [
                    ['category' => 'Claims', 'recordCount' => 1, 'cents' => 3000],
                    ['category' => 'Meeting', 'recordCount' => 1, 'cents' => 2000],
                ]
                && $position['currentDayongBalanceCents'] === 215000
                && $position['ledger']->count() === 2)
            ->assertSee('Current Dayong financial position')
            ->assertSee('Less: Claims')->assertSee('Less: Meeting')->assertSee('Total disbursements')
            ->assertSee('₱2,200.00')->assertSee('₱30.00')->assertSee('₱20.00')->assertSee('₱50.00')->assertSee('₱2,150.00');

        $viewer = User::factory()->create(['active' => true, 'is_admin' => false, 'legacy_permissions' => ['collections.view']]);
        $this->actingAs($viewer);
        Livewire::test(CollectionStatistics::class)
            ->assertViewHas('canViewFinancialPosition', false)
            ->assertDontSee('Current Dayong financial position');
    }

    public function test_yearly_filter_combines_registration_and_annual_dues_in_one_summary(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $registration = CollectionCycle::create(['name' => 'CY 2026 Registration Fee', 'type' => 'Registration Fee', 'expected_amount' => 100]);
        $annual = CollectionCycle::create(['name' => 'CY 2026 Annual Dues', 'type' => 'Annual Dues', 'expected_amount' => 100]);
        $new = Member::create(['first_name' => 'New', 'last_name' => 'Member', 'council' => 'A', 'registration_date' => '2026-01-01']);
        $old = Member::create(['first_name' => 'Old', 'last_name' => 'Member', 'council' => 'A', 'registration_date' => '2025-01-01']);
        $new->payments()->create(['collection_cycle_id' => $registration->id, 'amount' => 100]);
        $old->payments()->create(['collection_cycle_id' => $annual->id, 'amount' => 100]);

        $page = Livewire::test(CollectionStatistics::class)
            ->filterTable('cycles', [$registration->id])
            ->assertViewHas('cycleSummaries', fn ($summaries) => $summaries->count() === 1
                && $summaries->first()['name'] === '2026 Annual Dues / Registration'
                && $summaries->first()['totals']['paid'] === 2
                && $summaries->first()['totals']['unpaid'] === 0
                && $summaries->first()['totals']['amount_cents'] === 20000);
        $this->assertSame([$registration->id => '2026 Annual Dues / Registration'], $page->instance()->getTable()->getFilter('cycles')->getOptions());
        Livewire::test(\App\Filament\Resources\Members\Pages\ListMembers::class)
            ->assertSee('2026 Annual Dues / Registration')
            ->set('unpaidCycleIds', [$registration->id])->assertCountTableRecords(0);
        $old->payments()->delete();
        Livewire::test(\App\Filament\Resources\Members\Pages\ListMembers::class)
            ->set('unpaidCycleIds', [$registration->id])->assertCanSeeTableRecords([$old])->assertCanNotSeeTableRecords([$new]);
    }

    public function test_counts_amounts_and_live_cycle_filters(): void
    {
        $first = CollectionCycle::create(['name' => 'First cycle', 'type' => 'Dayong', 'expected_amount' => 100]);
        $second = CollectionCycle::create(['name' => 'Second cycle', 'type' => 'Dayong', 'expected_amount' => 100, 'active' => false]);
        $paid = Member::create(['first_name' => 'Paid', 'last_name' => 'Member', 'council' => '10']);
        $partial = Member::create(['first_name' => 'Partial', 'last_name' => 'Member', 'council' => '10']);
        $later = Member::create(['first_name' => 'Later', 'last_name' => 'Member', 'council' => '20', 'start_cycle_id' => $second->id]);
        Member::create(['first_name' => 'Unpaid', 'last_name' => 'Member', 'council' => '20']);
        $paid->payments()->create(['collection_cycle_id' => $first->id, 'amount' => 100.25]);
        $paid->payments()->create(['collection_cycle_id' => $second->id, 'amount' => 100]);
        $partial->payments()->create(['collection_cycle_id' => $first->id, 'amount' => 25.50]);
        $partial->payments()->create(['collection_cycle_id' => $second->id, 'amount' => 0]);
        $later->payments()->create(['collection_cycle_id' => $second->id, 'amount' => 100]);

        $report = app(CollectionStatisticsReport::class)->generate();
        $this->assertSame(['paid' => 2, 'unpaid' => 2, 'payment_records' => 5, 'zero_payment_records' => 1, 'amount_cents' => 32575], $report['totals']);
        $this->assertEquals(['council' => '10', 'paid' => 1, 'unpaid' => 1, 'payment_records' => 4, 'zero_payment_records' => 1, 'amount_cents' => 22575], $report['rows'][0]);
        $this->assertEquals(['council' => '20', 'paid' => 1, 'unpaid' => 1, 'payment_records' => 1, 'zero_payment_records' => 0, 'amount_cents' => 10000], $report['rows'][1]);
        $this->assertSame('Partial Member', $report['zeroPayments']->first()['member']);

        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $this->get('/admin/collection-statistics')->assertOk()
            ->assertSee('Collections per council')
            ->assertSee('100.00')
            ->assertSee('Payment records with no amount')
            ->assertSee('Partial Member')
            ->assertSee('Review payment');
        Livewire::test(CollectionStatistics::class)
            ->assertTableFilterExists('cycles')
            ->assertTableFilterExists('payment_date')
            ->assertSet('tableFilters.cycles.values', [])
            ->assertSeeHtml('class="council-summary-row"')
            ->assertSeeHtml('class="council-cycle-row"')
            ->assertViewHas('totals', $report['totals'])
            ->filterTable('cycles', [$first->id])
            ->assertViewHas('totals', ['paid' => 2, 'unpaid' => 1, 'payment_records' => 2, 'zero_payment_records' => 0, 'amount_cents' => 12575])
            ->filterTable('cycles', [$second->id])
            ->assertViewHas('totals', ['paid' => 2, 'unpaid' => 2, 'payment_records' => 3, 'zero_payment_records' => 1, 'amount_cents' => 20000])
            ->filterTable('cycles', [$first->id, $second->id])
            ->assertViewHas('cycleSummaries', function ($summaries) use ($first, $second) {
                $this->assertSame([$second->id, $first->id], $summaries->pluck('id')->all());
                $this->assertSame(20000, $summaries[0]['totals']['amount_cents']);
                $this->assertSame(12575, $summaries[1]['totals']['amount_cents']);
                $this->assertSame(2, $summaries[0]['totals']['unpaid']);
                $this->assertSame(1, $summaries[1]['totals']['unpaid']);

                return true;
            })
            ->assertSeeHtml('wire:key="cycle-summary-'.$first->id.'"')
            ->assertSeeHtml('wire:key="cycle-summary-'.$second->id.'"')
            ->assertViewHas('rows', function ($rows) use ($first, $second) {
                $council = $rows->firstWhere('council', '10');
                $this->assertSame([$second->id, $first->id], array_column($council['cycles'], 'id'));
                $this->assertSame(10000, $council['cycles'][0]['amount_cents']);
                $this->assertSame(12575, $council['cycles'][1]['amount_cents']);
                $this->assertSame(1, $council['cycles'][0]['paid']);
                $this->assertSame(2, $council['cycles'][1]['paid']);

                return true;
            })
            ->assertSeeHtml('class="council-cycle-row"')
            ->assertViewHas('totals', $report['totals'])
            ->resetTableFilters()
            ->assertViewHas('totals', $report['totals'])
            ->filterTable('cycles', [])
            ->assertViewHas('totals', $report['totals']);

        $partial->payments()->where('collection_cycle_id', $second->id)->update(['amount' => 100]);
        $this->assertSame(['paid' => 3, 'unpaid' => 1, 'payment_records' => 5, 'zero_payment_records' => 0, 'amount_cents' => 42575], app(CollectionStatisticsReport::class)->generate()['totals']);
    }

    public function test_year_range_includes_all_financial_activity_and_previous_year_balance(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 4));
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $first = CollectionCycle::create(['name' => '2024 Dayong', 'type' => 'Dayong', 'expected_amount' => 100, 'start_date' => '2024-01-01']);
        $second = CollectionCycle::create(['name' => '2025 Dayong', 'type' => 'Dayong', 'expected_amount' => 100, 'start_date' => '2025-01-01']);
        $third = CollectionCycle::create(['name' => '2026 Dayong', 'type' => 'Dayong', 'expected_amount' => 100, 'start_date' => '2026-01-01']);
        $member = Member::create(['first_name' => 'Paid', 'last_name' => 'Member', 'council' => 'A']);
        foreach ([$first, $second, $third] as $cycle) {
            $member->payments()->create(['collection_cycle_id' => $cycle->id, 'amount' => 100, 'date_paid' => $cycle->start_date]);
        }
        Member::create(['first_name' => 'Late', 'last_name' => 'Member', 'council' => 'A'])
            ->payments()->create(['collection_cycle_id' => $first->id, 'amount' => 50, 'date_paid' => '2025-02-01']);
        Disbursement::create(['disbursement_date' => '2024-06-01', 'category' => 'Meeting', 'amount' => 10]);
        Disbursement::create(['disbursement_date' => '2025-06-01', 'category' => 'Meeting', 'amount' => 20]);
        Disbursement::create(['disbursement_date' => '2026-06-01', 'category' => 'Meeting', 'amount' => 30]);
        $this->travelTo(now()->setDate(2024, 7, 1));
        Payable::create(['payee' => 'Carry', 'category' => 'Others', 'particulars' => 'Prior commitment', 'amount' => 40]);
        $this->travelTo(now()->setDate(2025, 7, 1));
        Payable::create(['payee' => 'Florist', 'category' => 'Others', 'particulars' => 'Wreath', 'amount' => 25]);
        $this->travelTo(now()->setDate(2026, 7, 1));
        Payable::create(['payee' => 'Later', 'category' => 'Others', 'particulars' => 'Later commitment', 'amount' => 60]);
        $this->travelTo(now()->setDate(2026, 10, 4));

        $page = Livewire::test(CollectionStatistics::class)
            ->assertSee('Report years')
            ->assertSee('Start year')
            ->assertSee('End year')
            ->set('startYear', '2025')
            ->assertViewHas('cycleSummaries', fn ($summaries) => $summaries->pluck('id')->all() === [$third->id, $second->id])
            ->assertViewHas('totals', fn ($totals) => $totals['amount_cents'] === 20000)
            ->assertViewHas('yearFinancial', fn ($financial) => $financial['openingBalanceCents'] === 209000
                && $financial['collectionCents'] === 25000 && $financial['disbursementCents'] === 5000
                && $financial['endingBalanceCents'] === 229000 && $financial['outstandingPayablesCents'] === 12500)
            ->set('endYear', '2025')
            ->assertViewHas('cycleSummaries', fn ($summaries) => $summaries->pluck('id')->all() === [$second->id])
            ->assertViewHas('totals', fn ($totals) => $totals['amount_cents'] === 10000)
            ->assertViewHas('yearFinancial', fn ($financial) => $financial['openingDate']->toDateString() === '2024-12-31'
                && $financial['openingBalanceCents'] === 209000
                && $financial['collectionCents'] === 15000
                && $financial['disbursementCents'] === 2000
                && $financial['endingBalanceCents'] === 222000
                && $financial['outstandingPayablesCents'] === 6500
                && $financial['availableBalanceCents'] === 215500
                && $financial['paymentItems']->pluck('cents', 'cycle')->all() === ['2024 Dayong' => 5000, '2025 Dayong' => 10000]
                && $financial['outstandingPayables']->pluck('payee')->all() === ['Carry', 'Florist'])
            ->assertSee('Showing 2025.')
            ->assertSee('Beginning balance')
            ->assertSee('Ending actual balance')
            ->assertSee('Florist')
            ->assertDontSee('Later commitment')
            ->filterTable('cycles', [$first->id])
            ->assertViewHas('cycleSummaries', fn ($summaries) => $summaries->isEmpty())
            ->assertViewHas('totals', ['paid' => 0, 'unpaid' => 0, 'payment_records' => 0, 'zero_payment_records' => 0, 'amount_cents' => 0])
            ->assertViewHas('yearFinancial', fn ($financial) => $financial['collectionCents'] === 15000)
            ->filterTable('cycles', [])
            ->assertViewHas('totals', fn ($totals) => $totals['amount_cents'] === 10000)
            ->callAction('downloadReport')
            ->assertHasNoActionErrors()
            ->assertFileDownloaded('collection-statistics-'.now()->format('Y-m-d').'.pdf', contentType: 'application/pdf');

        $page->call('clearYearRange')
            ->assertSet('startYear', null)
            ->assertSet('endYear', null)
            ->assertViewHas('cycleSummaries', fn ($summaries) => $summaries->count() === 3);
    }

    public function test_year_range_respects_financial_permissions(): void
    {
        CollectionCycle::create(['name' => '2025 Dayong', 'type' => 'Dayong', 'expected_amount' => 100]);
        $viewer = User::factory()->create(['active' => true, 'is_admin' => false, 'legacy_permissions' => ['collections.view']]);
        $this->actingAs($viewer);
        Livewire::test(CollectionStatistics::class)
            ->set('startYear', '2025')
            ->assertViewHas('yearFinancial', null)
            ->assertDontSee('Ending actual balance');
    }

    public function test_empty_report_and_invalid_cycle_do_not_fall_back_to_all_cycles(): void
    {
        $report = app(CollectionStatisticsReport::class);
        $this->assertSame(['paid' => 0, 'unpaid' => 0, 'payment_records' => 0, 'zero_payment_records' => 0, 'amount_cents' => 0], $report->generate()['totals']);
        Member::create(['first_name' => 'Test', 'last_name' => 'Member', 'council' => '10']);
        CollectionCycle::create(['name' => 'Cycle', 'type' => 'Dayong', 'expected_amount' => 100]);
        $this->assertSame(['paid' => 0, 'unpaid' => 0, 'payment_records' => 0, 'zero_payment_records' => 0, 'amount_cents' => 0], $report->generate([999])['totals']);
    }

    public function test_member_starting_in_third_cycle_is_absent_from_second_cycle_statistics(): void
    {
        $second = CollectionCycle::create(['name' => 'Second cycle', 'type' => 'Dayong', 'expected_amount' => 200]);
        $third = CollectionCycle::create(['name' => 'Third cycle', 'type' => 'Dayong', 'expected_amount' => 200]);
        $earlier = Member::create(['first_name' => 'Earlier', 'last_name' => 'Member', 'council' => '10', 'start_cycle_id' => $second->id]);
        $later = Member::create(['first_name' => 'Later', 'last_name' => 'Member', 'council' => '20', 'start_cycle_id' => $third->id]);
        $earlier->payments()->create(['collection_cycle_id' => $second->id, 'amount' => 200]);
        // Even a mistakenly recorded earlier payment must not include this member in the second cycle.
        $later->payments()->create(['collection_cycle_id' => $second->id, 'amount' => 200]);
        $later->payments()->create(['collection_cycle_id' => $third->id, 'amount' => 200]);

        $report = app(CollectionStatisticsReport::class);
        $secondReport = $report->generate([$second->id]);
        $this->assertSame(['paid' => 1, 'unpaid' => 0, 'payment_records' => 1, 'zero_payment_records' => 0, 'amount_cents' => 20000], $secondReport['totals']);
        $this->assertSame([10], $secondReport['rows']->pluck('council')->all());
        $thirdReport = $report->generate([$third->id]);
        $this->assertSame(['paid' => 1, 'unpaid' => 1, 'payment_records' => 1, 'zero_payment_records' => 0, 'amount_cents' => 20000], $thirdReport['totals']);
        $this->assertSame([10, 20], $thirdReport['rows']->pluck('council')->all());

        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $page = Livewire::test(CollectionStatistics::class)
            ->filterTable('cycles', [$second->id])->assertViewHas('totals', $secondReport['totals']);
        $this->assertCount(1, $page->instance()->getTableRecords());
        $page->filterTable('cycles', [$third->id])->assertViewHas('totals', $thirdReport['totals']);
        $this->assertCount(2, $page->instance()->getTableRecords());
    }

    public function test_payment_date_range_filters_counts_and_amounts_inclusively(): void
    {
        $cycle = CollectionCycle::create(['name' => 'Second cycle', 'type' => 'Dayong', 'expected_amount' => 200]);
        foreach ([
            ['Before', '2026-09-01', 200],
            ['From', '2026-09-15', 200],
            ['Through', '2026-10-01', 200],
            ['Undated', null, 200],
            ['Zero', '2026-09-15', 0],
        ] as [$name, $date, $amount]) {
            $member = Member::create(['first_name' => $name, 'last_name' => 'Member', 'council' => '10', 'start_cycle_id' => $cycle->id]);
            $member->payments()->create(['collection_cycle_id' => $cycle->id, 'amount' => $amount, 'date_paid' => $date]);
        }

        $report = app(CollectionStatisticsReport::class)->generate([$cycle->id], '2026-09-15', '2026-10-01');
        $this->assertSame(['paid' => 2, 'unpaid' => 3, 'payment_records' => 3, 'zero_payment_records' => 1, 'amount_cents' => 40000], $report['totals']);
        $this->assertSame('Zero Member', $report['zeroPayments']->first()['member']);

        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        Livewire::test(CollectionStatistics::class)
            ->filterTable('cycles', [$cycle->id])
            ->filterTable('payment_date', ['from' => '2026-09-15', 'to' => '2026-10-01'])
            ->assertViewHas('cycleSummaries', fn ($summaries) => $summaries->count() === 1 && $summaries->first()['totals'] === $report['totals'])
            ->assertViewHas('totals', $report['totals'])
            ->assertSee('400.00')
            ->filterTable('payment_date', ['from' => '2026-10-01', 'to' => '2026-10-01'])
            ->assertViewHas('totals', ['paid' => 1, 'unpaid' => 4, 'payment_records' => 1, 'zero_payment_records' => 0, 'amount_cents' => 20000])
            ->resetTableFilters()
            ->assertViewHas('totals', ['paid' => 4, 'unpaid' => 1, 'payment_records' => 5, 'zero_payment_records' => 1, 'amount_cents' => 80000]);
    }

    public function test_statistics_require_collection_view_permission_and_active_account(): void
    {
        $this->get('/admin/collection-statistics')->assertRedirect('/admin/login');
        $user = User::factory()->create(['active' => true, 'is_admin' => false]);
        $this->actingAs($user)->get('/admin/collection-statistics')->assertForbidden();
        $user->update(['legacy_permissions' => ['collections.view']]);
        $this->get('/admin/collection-statistics')->assertOk()->assertSee('No members yet');
        $user->update(['active' => false]);
        $this->get('/admin/collection-statistics')->assertForbidden();
    }
}
