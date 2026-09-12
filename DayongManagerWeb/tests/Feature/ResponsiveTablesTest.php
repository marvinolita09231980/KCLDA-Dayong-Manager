<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResponsiveTablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_tables_render_with_responsive_cards_and_actions(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        Member::create(['first_name' => 'Juan', 'last_name' => 'Cruz', 'council' => 'A']);
        foreach (['members', 'payments', 'collection-cycles', 'bank-transactions', 'disbursements', 'users'] as $page) {
            $this->get('/admin/'.$page)->assertOk()->assertSee('dayong-responsive-table');
        }
        $this->get('/admin/members')->assertSee('Record payment')->assertSee('data-mobile-label', false);
    }

    public function test_member_details_and_payment_prefill_work(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $member = Member::create(['first_name' => 'Ana', 'last_name' => 'Cruz', 'council' => 'Council 8814', 'contact_number' => '09123456789']);
        $cycle = \App\Models\CollectionCycle::create(['name' => 'Modal test cycle', 'type' => 'Dayong', 'expected_amount' => 125, 'active' => true]);
        \App\Models\Payment::create(['member_id' => $member->id, 'collection_cycle_id' => $cycle->id, 'amount' => 125, 'date_paid' => now(), 'receipt_number' => 'MODAL-RECEIPT', 'notes' => 'Member payment note']);
        $page = \Livewire\Livewire::test(\App\Filament\Resources\Members\Pages\ListMembers::class)
            ->mountTableAction('details', $member);
        $content = $page->instance()->getTable()->getAction('details')->record($member)->getModalContent()->render();
        foreach (['09123456789', 'Personal information', 'Beneficiary and claims', 'Payment history', 'Modal test cycle', 'MODAL-RECEIPT', 'Member payment note', '125.00', 'payment-history-table'] as $text) {
            $this->assertStringContainsString($text, $content);
        }
        \Livewire\Livewire::withQueryParams(['member_id' => $member->id])
            ->test(\App\Filament\Resources\Payments\Pages\CreatePayment::class)
            ->assertSet('data.member_id', $member->id);
    }
}
