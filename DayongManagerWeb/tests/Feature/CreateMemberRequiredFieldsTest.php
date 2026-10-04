<?php

namespace Tests\Feature;

use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Models\CollectionCycle;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateMemberRequiredFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_date_and_start_cycle_are_required_but_optional_details_can_be_blank(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $cycle = CollectionCycle::create(['name' => 'September', 'type' => 'Dayong', 'expected_amount' => 100, 'active' => true]);

        $page = Livewire::test(CreateMember::class)
            ->fillForm([
                'last_name' => 'Santos',
                'first_name' => 'Ana',
                'middle_name' => '',
                'address' => 'North',
                'council' => 'North',
                'sponsor_name' => '',
                'contact_number' => '',
                'beneficiary_name' => '',
                'beneficiary_contact' => '',
                'remarks' => '',
                'claimed_benefits' => '',
                'claim_received_by' => '',
                'registration_date' => null,
                'start_cycle_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['registration_date' => 'required', 'start_cycle_id' => 'required']);

        $page->fillForm(['registration_date' => '2026-09-18', 'start_cycle_id' => $cycle->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $member = Member::where('first_name', 'Ana')->firstOrFail();
        $this->assertSame($cycle->id, $member->start_cycle_id);
        $this->assertSame('', $member->middle_name);
        $this->assertSame('', $member->sponsor_name);
        $this->assertSame('', $member->contact_number);
        $this->assertSame('', $member->beneficiary_name);
        $this->assertSame('', $member->beneficiary_contact);
        $this->assertSame('', $member->claimed_benefits);
        $this->assertSame('', $member->claim_received_by);
        $this->assertSame('', $member->remarks);
    }

    public function test_existing_member_can_clear_sponsor_and_remarks(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $member = Member::create([
            'last_name' => 'Santos',
            'first_name' => 'Ana',
            'council' => 'North',
            'address' => 'North Street',
            'sponsor_name' => 'Old sponsor',
            'remarks' => 'Old remarks',
        ]);

        Livewire::test(EditMember::class, ['record' => $member->getRouteKey()])
            ->fillForm(['sponsor_name' => '', 'remarks' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('', $member->fresh()->sponsor_name);
        $this->assertSame('', $member->fresh()->remarks);
    }
}
