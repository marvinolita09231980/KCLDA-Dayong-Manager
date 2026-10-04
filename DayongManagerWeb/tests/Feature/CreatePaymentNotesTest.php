<?php

namespace Tests\Feature;

use App\Filament\Resources\Payments\Pages\CreatePayment;
use App\Models\CollectionCycle;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreatePaymentNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_zero_amount_cannot_be_saved_from_payment_form(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $member = Member::create(['first_name' => 'Ana', 'last_name' => 'Santos', 'council' => 'North']);
        $cycle = CollectionCycle::create(['name' => 'September', 'type' => 'Dayong', 'expected_amount' => 200]);

        Livewire::test(CreatePayment::class)
            ->fillForm(['member_id' => $member->id, 'collection_cycle_id' => $cycle->id, 'amount' => 0, 'receipt_number' => 'ZERO'])
            ->call('create')
            ->assertHasFormErrors(['amount' => 'min']);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_payment_can_be_created_without_notes(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $member = Member::create(['first_name' => 'Ana', 'last_name' => 'Santos', 'council' => 'North']);
        $cycle = CollectionCycle::create(['name' => 'September', 'type' => 'Dayong', 'expected_amount' => 100, 'active' => true]);

        Livewire::test(CreatePayment::class)
            ->fillForm([
                'member_id' => $member->id,
                'collection_cycle_id' => $cycle->id,
                'amount' => 100,
                'date_paid' => '2026-09-18',
                'receipt_number' => 'REC-1',
                'notes' => '',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('', Payment::where('receipt_number', 'REC-1')->firstOrFail()->notes);
    }

    public function test_same_member_cannot_pay_twice_for_same_cycle(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $member = Member::create(['first_name' => 'Ana', 'last_name' => 'Santos', 'council' => 'North']);
        $otherMember = Member::create(['first_name' => 'Ben', 'last_name' => 'Cruz', 'council' => 'North']);
        $cycle = CollectionCycle::create(['name' => 'September', 'type' => 'Dayong', 'expected_amount' => 100, 'active' => true]);
        Payment::create(['member_id' => $member->id, 'collection_cycle_id' => $cycle->id, 'amount' => 100, 'date_paid' => '2026-09-18', 'receipt_number' => 'FIRST', 'notes' => '']);

        $page = Livewire::test(CreatePayment::class)
            ->fillForm(['member_id' => $member->id, 'collection_cycle_id' => $cycle->id, 'amount' => 100, 'date_paid' => '2026-09-18', 'receipt_number' => 'DUPLICATE', 'notes' => ''])
            ->assertSee('Cycle amount: PHP 100.00')
            ->assertSee('Entered amount: PHP 100.00')
            ->assertSee('This member already paid PHP 100.00 for this cycle')
            ->call('create')
            ->assertHasFormErrors(['collection_cycle_id' => 'unique']);

        $this->assertDatabaseCount('payments', 1);

        $page->fillForm(['member_id' => $otherMember->id, 'receipt_number' => 'OTHER'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseCount('payments', 2);
    }
}
