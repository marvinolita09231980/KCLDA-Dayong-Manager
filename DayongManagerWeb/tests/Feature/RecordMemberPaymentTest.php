<?php

namespace Tests\Feature;

use App\Filament\Resources\Members\Pages\ListMembers;
use App\Models\CollectionCycle;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RecordMemberPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_saves_in_modal_without_leaving_members_and_rejects_duplicates(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $member = Member::create(['first_name' => 'Ana', 'last_name' => 'Cruz', 'council' => 'North']);
        $cycle = CollectionCycle::create(['name' => 'Test Collection', 'type' => 'Dayong', 'expected_amount' => 100, 'active' => true]);
        $data = ['collection_cycle_id' => $cycle->id, 'amount' => 100, 'date_paid' => '2026-09-12', 'receipt_number' => 'MODAL-123', 'notes' => 'Paid in person'];

        $page = Livewire::test(ListMembers::class)->searchTable('Ana');
        $page->mountTableAction('recordPayment', $member)->assertNoRedirect()
            ->unmountTableAction()->assertNoRedirect();
        $this->assertDatabaseCount('payments', 0);

        $page->callTableAction('recordPayment', $member, $data)
            ->assertHasNoTableActionErrors()->assertNoRedirect()
            ->assertSet('tableSearch', 'Ana')->assertNotified('Payment recorded');
        $this->assertDatabaseHas('payments', ['member_id' => $member->id, 'receipt_number' => 'MODAL-123', 'amount' => 100]);

        $page->mountTableAction('recordPayment', $member);
        $instance = $page->instance();
        $history = $instance->getSchema($instance->getMountedActionSchemaName())->toHtml();
        foreach (['Payment history', 'Test Collection', 'MODAL-123', 'Paid in person', '100.00', 'payment-history-table'] as $text) {
            $this->assertStringContainsString($text, $history);
        }
        $page->unmountTableAction();

        $page->callTableAction('recordPayment', $member, $data)
            ->assertHasTableActionErrors(['collection_cycle_id' => 'unique'])->assertNoRedirect();
        $this->assertDatabaseCount('payments', 1);
    }
}
