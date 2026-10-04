<?php

namespace Tests\Feature;

use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Widgets\DayongStatsOverview;
use App\Filament\Widgets\MemberPaymentStatusWidget;
use App\Models\{CollectionCycle, Member, Payment, User};
use App\Services\{CollectionStatisticsReport, MemberObligationReminder, PaymentReportExport};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DeceasedMemberExclusionTest extends TestCase
{
    use RefreshDatabase;

    public function test_historical_paid_member_counts_in_first_cycle_but_not_as_unpaid_in_next_cycle(): void
    {
        $first = CollectionCycle::create(['name' => 'First cycle', 'type' => 'Dayong', 'expected_amount' => 200, 'start_date' => '2026-01-01']);
        $second = CollectionCycle::create(['name' => 'Second cycle', 'type' => 'Dayong', 'expected_amount' => 200, 'start_date' => '2026-02-01']);
        $member = Member::create(['first_name' => 'Historical', 'last_name' => 'Member', 'council' => 'A', 'member_status' => 'Deceased', 'date_of_death' => '2026-01-15']);
        $member->payments()->create(['collection_cycle_id' => $first->id, 'amount' => 200, 'date_paid' => '2026-01-10']);
        $report = app(CollectionStatisticsReport::class);
        $this->assertSame(1, $report->generate([$first->id])['totals']['paid']);
        $this->assertSame(0, $report->generate([$second->id])['totals']['paid']);
        $this->assertSame(0, $report->generate([$second->id])['totals']['unpaid']);
        $all = $report->generate();
        $this->assertSame(1, $all['totals']['paid']);
        $this->assertSame(0, $all['totals']['unpaid']);
        $this->assertSame(20000, $all['totals']['amount_cents']);
        // Date filters change visible payments, not historical cycle membership.
        $this->assertSame(1, $report->generate([$first->id], '2026-01-11')['totals']['unpaid']);
        $first->update(['start_date' => null]);
        $second->update(['start_date' => null]);
        $member->update(['date_of_death' => null]);
        $this->assertSame(1, $report->generate([$first->id])['totals']['paid']);
        $this->assertSame(0, $report->generate([$second->id])['totals']['unpaid']);
    }

    public function test_deceased_members_are_excluded_from_active_and_unpaid_views_but_history_is_retained(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $cycle = CollectionCycle::create(['name' => 'Dayong cycle', 'type' => 'Dayong', 'expected_amount' => 100]);
        $living = Member::create(['first_name' => 'Living', 'last_name' => 'Member', 'council' => 'A', 'member_status' => 'Active']);
        $deceased = Member::create(['first_name' => 'Deceased', 'last_name' => 'Member', 'council' => 'A', 'member_status' => 'Deceased']);
        $dated = Member::create(['first_name' => 'Death recorded', 'last_name' => 'Member', 'council' => 'A', 'member_status' => 'Active', 'date_of_death' => '2026-01-01']);

        Livewire::test(MemberPaymentStatusWidget::class)
            ->assertCanSeeTableRecords([$living])->assertCanNotSeeTableRecords([$deceased, $dated]);
        Livewire::test(ListMembers::class)
            ->assertCanSeeTableRecords([$living, $deceased, $dated])
            ->filterTable('member_status', 'Active')
            ->assertCanSeeTableRecords([$living])->assertCanNotSeeTableRecords([$deceased, $dated])
            ->filterTable('member_status', null)
            ->set('unpaidCycleIds', [$cycle->id])
            ->assertCanSeeTableRecords([$living])->assertCanNotSeeTableRecords([$deceased, $dated]);

        $stats = (new class extends DayongStatsOverview {
            public function activeCount() { return $this->getStats()[0]->getValue(); }
        })->activeCount();
        $this->assertEquals(1, $stats);
        foreach ([$deceased, $dated] as $member) {
            $reminder = app(MemberObligationReminder::class)->prepare($member, []);
            $this->assertSame(0, $reminder['totalCents']);
            $this->assertSame('', $reminder['message']);
        }
        $report = app(PaymentReportExport::class)->report(true, Member::query(), [], [$cycle->id]);
        $this->assertCount(1, $report['rows']);
        $this->assertSame($living->full_name, $report['rows'][0][0]);
        $statistics = app(CollectionStatisticsReport::class)->generate();
        $this->assertSame(1, $statistics['totals']['unpaid']);

        $deceased->payments()->create(['collection_cycle_id' => $cycle->id, 'amount' => 100]);
        $paid = app(PaymentReportExport::class)->report(false, Payment::query(), [], [$cycle->id]);
        $this->assertCount(1, $paid['rows']);
        $this->assertSame($deceased->full_name, $paid['rows'][0][0]);
        $statistics = app(CollectionStatisticsReport::class)->generate();
        $this->assertSame(10000, $statistics['totals']['amount_cents']);
        $this->assertSame(1, $statistics['totals']['unpaid']);
        $this->assertSame(1, $statistics['deceasedPaidMembers']);
        $this->assertSame(1, $statistics['totals']['paid']);
        Livewire::test(\App\Filament\Pages\CollectionStatistics::class)
            ->assertViewHas('deceasedPaidMembers', 1)
            ->filterTable('payment_date', ['from' => '2026-01-01', 'to' => '2026-01-31'])
            ->assertViewHas('deceasedPaidMembers', 0);
    }
}
