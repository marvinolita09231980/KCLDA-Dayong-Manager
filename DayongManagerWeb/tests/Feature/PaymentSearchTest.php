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

        $table->searchTable('')
            ->filterTable('council', 'North Council')
            ->assertCanSeeTableRecords([$payment])
            ->assertCanNotSeeTableRecords([$otherPayment])
            ->assertCountTableRecords(1)
            ->filterTable('collection_cycle_id', $cycle->id)
            ->assertCountTableRecords(1)
            ->filterTable('council', 'South Council')
            ->assertCanSeeTableRecords([$otherPayment])
            ->assertCanNotSeeTableRecords([$payment])
            ->assertCountTableRecords(1)
            ->filterTable('council', null)
            ->assertCountTableRecords(2);
    }

    public function test_payments_can_be_filtered_by_collection_cycle(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));

        $member = Member::create(['first_name' => 'Elena', 'last_name' => 'Ardientes', 'council' => 'North Council']);
        $september = CollectionCycle::create(['name' => 'September Collection', 'type' => 'Dayong', 'expected_amount' => 100, 'active' => true]);
        $october = CollectionCycle::create(['name' => 'October Collection', 'type' => 'Dayong', 'expected_amount' => 100, 'active' => true]);
        $septemberPayment = Payment::create(['member_id' => $member->id, 'collection_cycle_id' => $september->id, 'amount' => 100, 'date_paid' => now(), 'receipt_number' => 'SEP-1']);
        $octoberPayment = Payment::create(['member_id' => $member->id, 'collection_cycle_id' => $october->id, 'amount' => 100, 'date_paid' => now(), 'receipt_number' => 'OCT-1']);

        Livewire::test(ListPayments::class)
            ->filterTable('collection_cycle_id', $september->id)
            ->assertCanSeeTableRecords([$septemberPayment])
            ->assertCanNotSeeTableRecords([$octoberPayment])
            ->filterTable('collection_cycle_id', $october->id)
            ->assertCanSeeTableRecords([$octoberPayment])
            ->assertCanNotSeeTableRecords([$septemberPayment]);
    }

    public function test_search_and_cycle_filter_remain_when_returning_to_payments_list(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));

        $member = Member::create(['first_name' => 'Elena', 'last_name' => 'Ardientes', 'council' => 'North Council']);
        $cycle = CollectionCycle::create(['name' => 'September Collection', 'type' => 'Dayong', 'expected_amount' => 100, 'active' => true]);
        $payment = Payment::create(['member_id' => $member->id, 'collection_cycle_id' => $cycle->id, 'amount' => 100, 'date_paid' => now(), 'receipt_number' => 'REC-1']);

        Livewire::test(ListPayments::class)
            ->searchTable('Elena')
            ->filterTable('council', 'North Council')
            ->filterTable('collection_cycle_id', $cycle->id)
            ->assertCanSeeTableRecords([$payment]);

        Livewire::test(ListPayments::class)
            ->assertSet('tableSearch', 'Elena')
            ->assertSet('tableFilters.council.value', 'North Council')
            ->assertSet('tableFilters.collection_cycle_id.values', [(string) $cycle->id])
            ->assertCanSeeTableRecords([$payment])
            ->assertCountTableRecords(1);
    }

    public function test_receipt_number_can_be_saved_in_the_table_cell(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));

        $member = Member::create(['first_name' => 'Elena', 'last_name' => 'Ardientes', 'council' => 'North Council']);
        $cycle = CollectionCycle::create(['name' => 'September Collection', 'type' => 'Dayong', 'expected_amount' => 100, 'active' => true]);
        $payment = Payment::create(['member_id' => $member->id, 'collection_cycle_id' => $cycle->id, 'amount' => 100, 'date_paid' => now(), 'receipt_number' => 'OLD-1']);

        $page = Livewire::test(ListPayments::class)
            ->assertSet('receiptEditing', false)
            ->call('updateTableColumnState', 'receipt_number', (string) $payment->id, 'BLOCKED-1');

        $this->assertSame('OLD-1', $payment->fresh()->receipt_number);

        $page->callAction('toggleReceiptEditing')
            ->assertSet('receiptEditing', true)
            ->call('updateTableColumnState', 'receipt_number', (string) $payment->id, 'NEW-1')
            ->assertHasNoErrors();

        $this->assertSame('NEW-1', $payment->fresh()->receipt_number);

        $page->callAction('toggleReceiptEditing')
            ->assertSet('receiptEditing', false)
            ->call('updateTableColumnState', 'receipt_number', (string) $payment->id, 'BLOCKED-2');

        $this->assertSame('NEW-1', $payment->fresh()->receipt_number);
    }

    public function test_view_only_user_cannot_change_receipt_number_in_the_table(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['active' => true, 'is_admin' => false]);
        $user->givePermissionTo('collections.view');
        $this->actingAs($user);

        $member = Member::create(['first_name' => 'Elena', 'last_name' => 'Ardientes', 'council' => 'North Council']);
        $cycle = CollectionCycle::create(['name' => 'September Collection', 'type' => 'Dayong', 'expected_amount' => 100, 'active' => true]);
        $payment = Payment::create(['member_id' => $member->id, 'collection_cycle_id' => $cycle->id, 'amount' => 100, 'date_paid' => now(), 'receipt_number' => 'OLD-1']);

        Livewire::test(ListPayments::class)
            ->set('receiptEditing', true)
            ->call('updateTableColumnState', 'receipt_number', (string) $payment->id, 'FORBIDDEN-1');

        $this->assertSame('OLD-1', $payment->fresh()->receipt_number);
    }
}
