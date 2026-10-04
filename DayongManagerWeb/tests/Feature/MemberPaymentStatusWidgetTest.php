<?php

namespace Tests\Feature;

use App\Filament\Resources\Payments\Pages\CreatePayment;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Widgets\MemberPaymentStatusWidget;
use App\Models\CollectionCycle;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MemberPaymentStatusWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_payment_records_and_unpaid_members_use_two_page_tabs(): void
    {
        $first = CollectionCycle::create(['name' => 'First cycle', 'type' => 'Dayong', 'expected_amount' => 200]);
        $second = CollectionCycle::create(['name' => 'Second cycle', 'type' => 'Dayong', 'expected_amount' => 200]);
        $third = CollectionCycle::create(['name' => 'Third cycle', 'type' => 'Dayong', 'expected_amount' => 200]);
        $paid = Member::create(['first_name' => 'Paid', 'last_name' => 'Member', 'council' => 'A', 'start_cycle_id' => $first->id]);
        $zero = Member::create(['first_name' => 'Zero', 'last_name' => 'Member', 'council' => 'A', 'start_cycle_id' => $first->id]);
        $missing = Member::create(['first_name' => 'Missing', 'last_name' => 'Member', 'council' => 'B', 'start_cycle_id' => $first->id]);
        $later = Member::create(['first_name' => 'Later', 'last_name' => 'Member', 'council' => 'C', 'start_cycle_id' => $third->id]);
        $paid->payments()->create(['collection_cycle_id' => $first->id, 'amount' => 200, 'date_paid' => '2026-09-01']);
        $paid->payments()->create(['collection_cycle_id' => $second->id, 'amount' => 200, 'date_paid' => '2026-09-15']);
        $zero->payments()->create(['collection_cycle_id' => $second->id, 'amount' => 0, 'date_paid' => '2026-09-15']);
        $later->payments()->create(['collection_cycle_id' => $second->id, 'amount' => 200, 'date_paid' => '2026-09-15']);
        $later->payments()->create(['collection_cycle_id' => $third->id, 'amount' => 200, 'date_paid' => '2026-10-01']);

        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $this->get('/admin/payments')->assertOk()
            ->assertSee('Payment filters')
            ->assertSee('Collection cycle')
            ->assertSee('Council')
            ->assertSee('Paid')
            ->assertSee('Unpaid')
            ->assertSee('Recorded payments');

        $page = Livewire::test(ListPayments::class)
            ->assertSet('activeTab', 'paid')
            ->assertTableFilterExists('council')
            ->assertTableFilterExists('collection_cycle_id')
            ->assertTableFilterExists('payment_date')
            ->filterTable('collection_cycle_id', $second->id)
            ->filterTable('council', 'A')
            ->assertCanSeeTableRecords([$paid->payments()->where('collection_cycle_id', $second->id)->firstOrFail()])
            ->assertCanNotSeeTableRecords([
                $zero->payments()->where('collection_cycle_id', $second->id)->firstOrFail(),
                $later->payments()->where('collection_cycle_id', $second->id)->firstOrFail(),
            ])
            ->set('activeTab', 'unpaid')
            ->assertSee('Unpaid members')
            ->assertDontSee('Recorded payments')
            ->assertSee('Zero Member')
            ->assertDontSee('Missing Member')
            ->assertDontSee('Later Member');

        $page->filterTable('council', 'B')
            ->assertSee('Missing Member')
            ->assertDontSee('Zero Member')
            ->set('activeTab', 'paid')
            ->assertCountTableRecords(0)
            ->filterTable('council', null)
            ->filterTable('payment_date', ['from' => '2026-09-16', 'to' => '2026-09-30'])
            ->assertCountTableRecords(0)
            ->set('activeTab', 'unpaid')
            ->assertSee('Paid Member')
            ->assertSee('Zero Member')
            ->assertSee('Missing Member');

        $page->filterTable('collection_cycle_id', $third->id)
            ->filterTable('payment_date', ['from' => '2026-10-01', 'to' => '2026-10-01'])
            ->assertDontSee('Later Member')
            ->call('resetTableFiltersForm')
            ->assertSet('tableFilters.collection_cycle_id.values', [])
            ->assertSet('tableFilters.council.value', null);

        $widget = Livewire::test(MemberPaymentStatusWidget::class, [
            'sharedFilters' => ['collection_cycle_id' => ['value' => $second->id]],
        ])
            ->assertCanSeeTableRecords([$zero, $missing])
            ->assertCanNotSeeTableRecords([$paid, $later])
            ->assertSee('Unpaid: Second cycle')
            ->assertSee('Review payment')
            ->assertSee('Record payment');

        Livewire::withQueryParams(['member_id' => $missing->id, 'collection_cycle_id' => $second->id])
            ->test(CreatePayment::class)
            ->assertSet('data.member_id', $missing->id)
            ->assertSet('data.collection_cycle_id', $second->id);
    }

    public function test_view_only_user_can_see_unpaid_members_without_payment_actions(): void
    {
        $cycle = CollectionCycle::create(['name' => 'Second cycle', 'type' => 'Dayong', 'expected_amount' => 200]);
        $missing = Member::create(['first_name' => 'Missing', 'last_name' => 'Member', 'council' => 'A']);
        $zero = Member::create(['first_name' => 'Zero', 'last_name' => 'Member', 'council' => 'A']);
        $zero->payments()->create(['collection_cycle_id' => $cycle->id, 'amount' => 0]);
        $this->actingAs(User::factory()->create(['active' => true, 'legacy_permissions' => ['collections.view']]));

        Livewire::test(MemberPaymentStatusWidget::class, [
            'sharedFilters' => ['collection_cycle_id' => ['value' => $cycle->id]],
        ])
            ->assertCanSeeTableRecords([$missing, $zero])
            ->assertDontSee('Record payment')
            ->assertDontSee('Review payment');
    }

    public function test_unpaid_member_table_requires_collection_view_permission(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'legacy_permissions' => []]));

        Livewire::test(MemberPaymentStatusWidget::class)->assertForbidden();
    }
}
