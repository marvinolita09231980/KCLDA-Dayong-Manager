<?php

namespace Tests\Feature;

use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Models\CollectionCycle;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentRowHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_row_opens_complete_history_for_only_its_member(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $member = Member::create(['first_name' => 'Ana', 'last_name' => 'Cruz', 'council' => '8814']);
        $other = Member::create(['first_name' => 'Ben', 'last_name' => 'Cruz', 'council' => '17822']);
        $first = CollectionCycle::create(['name' => 'First cycle', 'type' => 'Dayong', 'expected_amount' => 100]);
        $second = CollectionCycle::create(['name' => 'Second cycle', 'type' => 'Annual Dues', 'expected_amount' => 250]);
        $payment = Payment::create(['member_id' => $member->id, 'collection_cycle_id' => $first->id, 'amount' => 100, 'date_paid' => '2026-01-01', 'receipt_number' => 'OLDER-RECEIPT']);
        Payment::create(['member_id' => $member->id, 'collection_cycle_id' => $second->id, 'amount' => 250, 'date_paid' => '2026-02-01', 'receipt_number' => 'NEWER-RECEIPT', 'notes' => '<script>alert(1)</script>']);
        Payment::create(['member_id' => $other->id, 'collection_cycle_id' => $first->id, 'amount' => 999, 'receipt_number' => 'OTHER-MEMBER-RECEIPT']);

        $page = Livewire::test(ListPayments::class)->filterTable('collection_cycle_id', $first->id)->mountTableAction('details', $payment);
        $table = $page->instance()->getTable();
        $this->assertSame('details', $table->getRecordAction($payment));
        $action = $table->getAction('details')->record($payment);
        $this->assertSame('Member payment history', $action->getModalHeading());
        $content = $action->getModalContent()->render();
        foreach (['member-payments-table', 'Ana Cruz', '8814', 'First cycle', 'Second cycle', 'OLDER-RECEIPT', 'NEWER-RECEIPT', '350.00', '2 payments recorded'] as $text) {
            $this->assertStringContainsString($text, $content);
        }
        $this->assertStringNotContainsString('OTHER-MEMBER-RECEIPT', $content);
        $this->assertStringNotContainsString('<script>', $content);
        $this->assertLessThan(strpos($content, 'OLDER-RECEIPT'), strpos($content, 'NEWER-RECEIPT'));
    }
}
