<?php

namespace Tests\Feature;

use App\Filament\Resources\Members\Pages\ListMembers;
use App\Models\CollectionCycle;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UnpaidMemberFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_are_only_unpaid_from_their_starting_cycle_onward(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $cycles = collect(['First', 'Second', 'Third'])->map(fn ($name) => CollectionCycle::create(['name' => $name, 'type' => 'Dayong', 'expected_amount' => 100, 'active' => true]));
        $member = Member::create(['first_name' => 'Later', 'last_name' => 'Member', 'council' => 'North', 'start_cycle_id' => $cycles[1]->id]);

        $page = Livewire::test(ListMembers::class)
            ->set('unpaidCycleIds', [$cycles[0]->id])->assertCountTableRecords(0)
            ->set('unpaidCycleIds', [$cycles[1]->id])->assertCanSeeTableRecords([$member])
            ->set('unpaidCycleIds', [$cycles[0]->id, $cycles[1]->id])->assertCanSeeTableRecords([$member]);

        $page->set('unpaidCycleMatch', 'and')->assertCountTableRecords(0)
            ->set('unpaidCycleIds', [$cycles[1]->id, $cycles[2]->id])->assertCanSeeTableRecords([$member])
            ->set('unpaidCycleMatch', 'or');

        $member->payments()->create(['collection_cycle_id' => $cycles[1]->id, 'amount' => 100, 'date_paid' => now()]);

        $page->set('unpaidCycleIds', [$cycles[0]->id, $cycles[1]->id])->assertCountTableRecords(0)
            ->set('unpaidCycleIds', [$cycles[2]->id])->assertCanSeeTableRecords([$member]);
    }

    public function test_cycle_checkboxes_filter_unpaid_members_and_combine_with_council(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $cycles = collect(['First', 'Second'])->map(fn ($name) => CollectionCycle::create(['name' => $name, 'type' => 'Dayong', 'expected_amount' => 100, 'active' => true]));
        $members = collect(['Paid', 'MissingSecond', 'Unpaid', 'Zero', 'Partial'])->mapWithKeys(fn ($name) => [$name => Member::create(['first_name' => $name, 'last_name' => 'Test', 'council' => $name === 'Unpaid' ? 'South' : 'North'])]);
        foreach (['Paid' => [100, 100], 'MissingSecond' => [100], 'Zero' => [0, 0], 'Partial' => [50, 50]] as $name => $amounts) {
            foreach ($amounts as $index => $amount) {
                $members[$name]->payments()->create(['collection_cycle_id' => $cycles[$index]->id, 'amount' => $amount, 'date_paid' => now()]);
            }
        }

        $page = Livewire::test(ListMembers::class)
            ->assertSee('members-unpaid-header', false)
            ->set('unpaidCycleIds', [$cycles[0]->id])
            ->assertCanSeeTableRecords([$members['Unpaid'], $members['Zero']])
            ->assertCountTableRecords(2)
            ->set('unpaidCycleIds', $cycles->pluck('id')->all())
            ->assertCanSeeTableRecords([$members['MissingSecond'], $members['Unpaid'], $members['Zero']])
            ->assertCountTableRecords(3)
            ->set('unpaidCycleMatch', 'and')
            ->assertCanSeeTableRecords([$members['Unpaid'], $members['Zero']])
            ->assertCountTableRecords(2)
            ->filterTable('council', 'North')->assertCountTableRecords(1)
            ->set('unpaidCycleMatch', 'or')
            ->filterTable('council', 'North')->assertCountTableRecords(2)
            ->call('clearUnpaidCycles')->assertCountTableRecords(4);
    }
}
