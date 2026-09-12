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

class CreatePaymentHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_follows_selected_member_and_resets_pagination(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $member = Member::create(['first_name' => 'Ana', 'last_name' => 'Cruz', 'council' => 'North']);
        $other = Member::create(['first_name' => 'Juan', 'last_name' => 'Reyes', 'council' => 'South']);
        foreach (range(1, 11) as $number) {
            $cycle = CollectionCycle::create(['name' => 'Test Collection '.$number, 'type' => 'Dayong', 'expected_amount' => 100, 'active' => true]);
            Payment::create(['member_id' => $member->id, 'collection_cycle_id' => $cycle->id, 'amount' => 100, 'date_paid' => '2026-09-12', 'receipt_number' => 'ANA-'.str_pad($number, 3, '0', STR_PAD_LEFT)]);
        }
        Payment::create(['member_id' => $other->id, 'collection_cycle_id' => $cycle->id, 'amount' => 200, 'date_paid' => '2026-09-12', 'receipt_number' => 'JUAN-001']);

        Livewire::test(CreatePayment::class)
            ->assertSee('Select a member')
            ->set('data.member_id', $member->id)
            ->assertSee('ANA-011')->assertDontSee('ANA-001')->assertDontSee('JUAN-001')
            ->call('setPage', 2, 'paymentHistoryPage')
            ->assertSee('ANA-001')->assertDontSee('ANA-011')
            ->set('data.member_id', $other->id)
            ->assertSee('JUAN-001')->assertDontSee('ANA-001')
            ->set('data.member_id', null)
            ->assertSee('Select a member')->assertDontSee('JUAN-001');
    }
}
