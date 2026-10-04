<?php

namespace Tests\Feature;

use App\Filament\Widgets\MemberPaymentStatusWidget;
use App\Models\CollectionCycle;
use App\Models\Member;
use App\Models\User;
use App\Services\MemberObligationReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MemberObligationReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminder_uses_remaining_balance_and_credits_registration_and_payments_outside_date_filters(): void
    {
        $first = CollectionCycle::create(['name' => 'First cycle', 'type' => 'Dayong', 'expected_amount' => 200]);
        $second = CollectionCycle::create(['name' => 'Second cycle', 'type' => 'Dayong', 'expected_amount' => 100]);
        $registration = CollectionCycle::create(['name' => '2026 Registration', 'type' => 'Registration Fee', 'expected_amount' => 100]);
        CollectionCycle::create(['name' => '2026 Annual dues', 'type' => 'Annual Dues', 'expected_amount' => 100]);
        $member = Member::create(['first_name' => 'Ana', 'last_name' => 'Cruz', 'council' => 'North', 'registration_date' => '2026-01-01']);
        $member->payments()->create(['collection_cycle_id' => $first->id, 'amount' => '75.50', 'date_paid' => '2026-01-01']);
        $member->payments()->create(['collection_cycle_id' => $registration->id, 'amount' => 100, 'date_paid' => '2026-01-01']);
        $member->load(['payments' => fn ($query) => $query->whereDate('date_paid', '>=', '2026-09-01')]);
        $report = app(MemberObligationReminder::class)->prepare($member, ['payment_date' => ['from' => '2026-09-01']]);
        $this->assertSame(22450, $report['totalCents']);
        $this->assertStringContainsString('First cycle: PHP 124.50', $report['message']);
        $this->assertStringContainsString('Second cycle: PHP 100.00', $report['message']);
        $this->assertStringNotContainsString('Annual dues:', $report['message']);
        $report = app(MemberObligationReminder::class)->prepare($member, ['collection_cycle_id' => ['values' => [$registration->id]]]);
        $this->assertSame(0, $report['totalCents']);
        $this->assertSame('', $report['message']);
    }

    public function test_unpaid_action_opens_review_modal_without_sending_a_message(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'legacy_permissions' => ['collections.view']]));
        CollectionCycle::create(['name' => 'Third cycle', 'type' => 'Dayong', 'expected_amount' => 100]);
        $member = Member::create(['first_name' => 'Ana', 'last_name' => 'Cruz', 'council' => 'North']);
        $page = Livewire::test(MemberPaymentStatusWidget::class)->mountTableAction('obligationReminder', $member);
        $content = $page->instance()->getMountedAction()->getModalContent()->render();
        $this->assertStringContainsString('Hello Ana Cruz', $content);
        $this->assertStringContainsString('Amount to be collected: PHP 100.00', $content);
        $this->assertStringContainsString('Copy message', $content);
        $this->assertStringContainsString('Open Messenger', $content);
    }
}
