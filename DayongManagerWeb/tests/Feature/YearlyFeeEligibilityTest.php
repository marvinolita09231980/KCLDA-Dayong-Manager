<?php

namespace Tests\Feature;

use App\Models\{CollectionCycle, Member, User};
use App\Services\{PaymentCycleColumns, MemberObligationReminder, ComplianceReport};
use App\Filament\Widgets\MemberPaymentStatusWidget;
use App\Filament\Resources\Payments\Pages\CreatePayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class YearlyFeeEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_applicable_fee_clears_the_balance_and_unpaid_list(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $registration = CollectionCycle::create(['name' => 'Registration 2027', 'type' => 'Registration Fee', 'expected_amount' => 200]);
        $annual = CollectionCycle::create(['name' => 'Annual 2027', 'type' => 'Annual Dues', 'expected_amount' => 100]);
        $new = Member::create(['first_name' => 'New', 'last_name' => 'Member', 'council' => 'A', 'registration_date' => '2027-01-01']);
        $old = Member::create(['first_name' => 'Old', 'last_name' => 'Member', 'council' => 'A', 'registration_date' => '2026-01-01']);
        // Historical misclassified payments must remain recorded without clearing a different obligation.
        $new->payments()->create(['collection_cycle_id' => $annual->id, 'amount' => 100]);
        $old->payments()->create(['collection_cycle_id' => $registration->id, 'amount' => 200]);
        $column = PaymentCycleColumns::all()->first();
        $this->assertSame(20000, PaymentCycleColumns::outstandingCents($new, $column));
        $this->assertSame(10000, PaymentCycleColumns::outstandingCents($old, $column));
        $this->assertSame(20000, app(MemberObligationReminder::class)->prepare($new, [])['totalCents']);
        $this->assertSame(10000, app(MemberObligationReminder::class)->prepare($old, [])['totalCents']);
        Livewire::test(MemberPaymentStatusWidget::class)->assertCanSeeTableRecords([$new, $old]);

        $new->payments()->create(['collection_cycle_id' => $registration->id, 'amount' => 200]);
        $old->payments()->create(['collection_cycle_id' => $annual->id, 'amount' => 100]);
        $this->assertSame(0, PaymentCycleColumns::outstandingCents($new->fresh(), $column));
        $this->assertSame(0, PaymentCycleColumns::outstandingCents($old->fresh(), $column));
        Livewire::test(MemberPaymentStatusWidget::class)->assertCanNotSeeTableRecords([$new, $old]);
        $statistics = app(\App\Services\CollectionStatisticsReport::class)->generate([$registration->id, $annual->id]);
        $this->assertSame(2, $statistics['totals']['paid']);
        $this->assertSame(0, $statistics['totals']['unpaid']);
        $rows = app(ComplianceReport::class)->rows()->keyBy('id');
        $this->assertSame('Registration Fee', $rows[$new->id]['payment_cycles']->first()['type']);
        $this->assertSame('Annual Dues', $rows[$old->id]['payment_cycles']->first()['type']);
    }

    public function test_non_applicable_fee_is_not_charged_when_only_one_cycle_exists(): void
    {
        $registration = CollectionCycle::create(['name' => 'Registration 2027', 'type' => 'Registration Fee', 'expected_amount' => 200]);
        $old = Member::create(['first_name' => 'Old', 'last_name' => 'Member', 'council' => 'A', 'registration_date' => '2026-01-01']);
        $column = PaymentCycleColumns::all()->first();
        $this->assertFalse(PaymentCycleColumns::applicable($old, $column));
        $this->assertSame(0, PaymentCycleColumns::outstandingCents($old, $column));
        $this->assertFalse(PaymentCycleColumns::whereApplicable(Member::query(), $column)->whereKey($old->id)->exists());
        $registration->update(['type' => 'Annual Dues']);
        $new = Member::create(['first_name' => 'New', 'last_name' => 'Member', 'council' => 'A', 'registration_date' => '2027-01-01']);
        $column = PaymentCycleColumns::all()->first();
        $this->assertFalse(PaymentCycleColumns::applicable($new, $column));
        $this->assertFalse(PaymentCycleColumns::whereApplicable(Member::query(), $column)->whereKey($new->id)->exists());
    }

    public function test_payment_entry_rejects_the_wrong_fee_for_the_year(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $registration = CollectionCycle::create(['name' => 'Registration 2027', 'type' => 'Registration Fee', 'expected_amount' => 100]);
        $annual = CollectionCycle::create(['name' => 'Annual 2027', 'type' => 'Annual Dues', 'expected_amount' => 100]);
        foreach ([['2027-01-01', $annual], ['2026-01-01', $registration]] as [$date, $cycle]) {
            $member = Member::create(['first_name' => 'Test', 'last_name' => $date, 'council' => 'A', 'registration_date' => $date]);
            Livewire::test(CreatePayment::class)->fillForm(['member_id' => $member->id, 'collection_cycle_id' => $cycle->id, 'amount' => 100, 'receipt_number' => 'TEST'])
                ->call('create')->assertHasFormErrors(['collection_cycle_id']);
        }
        $this->assertDatabaseCount('payments', 0);
    }
}
