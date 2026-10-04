<?php

namespace Tests\Feature;

use App\Filament\Resources\Payments\Pages\EditPayment;
use App\Models\CollectionCycle;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EditPaymentNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_can_be_edited_with_blank_notes(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));

        $member = Member::create(['first_name' => 'Ana', 'last_name' => 'Santos', 'council' => 'North']);
        $cycle = CollectionCycle::create(['name' => 'September', 'type' => 'Dayong', 'expected_amount' => 100, 'active' => true]);
        $payment = Payment::create([
            'member_id' => $member->id,
            'collection_cycle_id' => $cycle->id,
            'amount' => 100,
            'date_paid' => '2026-09-18',
            'receipt_number' => 'REC-1',
            'notes' => 'Old note',
        ]);

        Livewire::test(EditPayment::class, ['record' => $payment->getRouteKey()])
            ->fillForm(['notes' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('', $payment->fresh()->notes);
    }
}
