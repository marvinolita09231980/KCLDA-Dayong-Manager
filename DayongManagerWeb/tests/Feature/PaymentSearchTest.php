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

class PaymentSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_payments_can_be_searched_by_member_and_payment_details(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));

        $cycle = CollectionCycle::create(['name' => 'September Collection', 'type' => 'Dayong', 'expected_amount' => 100, 'active' => true]);
        $member = Member::create(['first_name' => 'Elena', 'last_name' => 'Ardientes', 'council' => 'North Council']);
        $otherMember = Member::create(['first_name' => 'Juan', 'last_name' => 'Cruz', 'council' => 'South Council']);
        $payment = Payment::create(['member_id' => $member->id, 'collection_cycle_id' => $cycle->id, 'amount' => 100, 'date_paid' => now(), 'receipt_number' => 'RECEIPT-123']);
        $otherPayment = Payment::create(['member_id' => $otherMember->id, 'collection_cycle_id' => $cycle->id, 'amount' => 100, 'date_paid' => now(), 'receipt_number' => 'RECEIPT-456']);

        $table = Livewire::test(ListPayments::class);

        foreach (['elena', 'ardientes', 'North', 'RECEIPT-123'] as $search) {
            $table->searchTable($search)
                ->assertCanSeeTableRecords([$payment])
                ->assertCanNotSeeTableRecords([$otherPayment])
                ->assertCountTableRecords(1);
        }

        $table->searchTable('September')
            ->assertCanSeeTableRecords([$payment, $otherPayment])
            ->assertCountTableRecords(2);

        $table->searchTable('nonexistent')
            ->assertCountTableRecords(0);
    }
}
