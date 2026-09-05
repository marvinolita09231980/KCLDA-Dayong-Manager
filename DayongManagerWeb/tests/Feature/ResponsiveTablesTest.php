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
        \Livewire\Livewire::test(\App\Filament\Resources\Members\Pages\ListMembers::class)
            ->mountTableAction('details', $member)->assertSee('09123456789');
        \Livewire\Livewire::withQueryParams(['member_id' => $member->id])
            ->test(\App\Filament\Resources\Payments\Pages\CreatePayment::class)
            ->assertSet('data.member_id', $member->id);
    }
}
