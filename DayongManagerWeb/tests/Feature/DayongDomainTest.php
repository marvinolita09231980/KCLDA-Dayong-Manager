<?php

namespace Tests\Feature;

use App\Models\CollectionCycle;
use App\Models\Member;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DayongDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_payment_is_linked_to_a_collection_cycle(): void
    {
        $cycle = CollectionCycle::create(['name'=>'Test Dayong','type'=>'Dayong','expected_amount'=>100,'active'=>true]);
        $member = Member::create(['last_name'=>'Dela Cruz','first_name'=>'Juan','council'=>'Test Council']);
        $payment = Payment::create(['member_id'=>$member->id,'collection_cycle_id'=>$cycle->id,'amount'=>100,'date_paid'=>now()]);

        $this->assertSame('Juan Dela Cruz', $payment->member->full_name);
        $this->assertSame('Test Dayong', $payment->collectionCycle->name);
    }
}
