<?php

namespace Tests\Feature;

use App\Models\CollectionCycle;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use App\Services\ComplianceReport;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ComplianceAndToolsTest extends TestCase
{
    use DatabaseMigrations;

    public function test_status_modal_updates_only_the_selected_member_status(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $member = Member::create(['first_name' => 'Ana', 'last_name' => 'Cruz', 'council' => 'A', 'remarks' => 'Existing remarks']);
        \Livewire\Livewire::test(\App\Filament\Pages\Compliance::class)
            ->mountAction('changeStatus', ['member' => $member->id])
            ->setActionData(['member_status' => 'Inactive'])
            ->callMountedAction()->assertHasNoActionErrors();
        $this->assertSame('Inactive', $member->fresh()->member_status);
        $this->assertSame('Existing remarks', $member->fresh()->remarks);
    }

    public function test_member_payment_details_include_all_cycles_and_recorded_amounts(): void
    {
        $member = Member::create(['first_name' => 'Ana', 'last_name' => 'Cruz', 'council' => 'A', 'member_status' => 'Deceased']);
        $cycle = CollectionCycle::create(['name' => 'Annual 2027', 'type' => 'Annual Dues', 'expected_amount' => 100]);
        CollectionCycle::create(['name' => 'Dayong cycle', 'type' => 'Dayong', 'expected_amount' => 200]);
        Payment::create(['member_id' => $member->id, 'collection_cycle_id' => $cycle->id, 'amount' => 40, 'date_paid' => '2027-01-12', 'receipt_number' => 'OR-123', 'notes' => 'First installment']);
        $cycles = app(ComplianceReport::class)->rows()->first()['payment_cycles'];
        $this->assertCount(2, $cycles);
        $this->assertSame('Partial payment', $cycles->first()['status']);
        $this->assertSame('Jan 12, 2027', $cycles->first()['date']);
        $this->assertSame('OR-123', $cycles->first()['receipt']);
        $this->assertSame('No payment recorded', $cycles->last()['status']);
        $this->assertEquals(40, $cycles->sum('paid'));
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]))
            ->get('/admin/compliance')->assertOk()->assertSee('OR-123')->assertSee('First installment')->assertSee('Dayong cycle');
    }

    public function test_guests_are_redirected_to_the_panel_login(): void
    {
        $this->get('/admin/data-tools/template')->assertRedirect('/admin/login');
        $this->post('/admin/data-tools/backup')->assertRedirect('/admin/login');
    }

    public function test_pages_render_for_admin_and_tools_reject_unprivileged_and_inactive_users(): void
    {
        $admin = User::factory()->create(['active' => true, 'is_admin' => true]);
        Member::create(['first_name' => 'Juan', 'last_name' => 'Cruz', 'council' => 'Council A']);
        $this->actingAs($admin)->get('/admin/compliance')->assertOk()->assertSee('Good Standing')->assertSee('Juan Cruz');
        $this->get('/admin/data-tools')->assertOk()->assertSee('Import members')->assertSee('Export CSV')->assertSee('Back up and download');
        $user = User::factory()->create(['active' => true, 'is_admin' => false]);
        $this->actingAs($user)->get('/admin/data-tools')->assertForbidden();
        foreach (['backup', 'export', 'import-members', 'import-windows'] as $action) {
            $this->post('/admin/data-tools/'.$action)->assertForbidden();
        }
        $admin->update(['active' => false]);
        $this->actingAs($admin)->post('/admin/data-tools/backup')->assertForbidden();
    }

    public function test_compliance_uses_start_cycle_latest_two_and_annual_rollover(): void
    {
        $this->travelTo(now()->setDate(2027, 3, 2));
        CollectionCycle::create(['name' => 'Old', 'type' => 'Dayong', 'expected_amount' => 100]);
        $first = CollectionCycle::create(['name' => 'First', 'type' => 'Dayong', 'expected_amount' => 100]);
        $second = CollectionCycle::create(['name' => 'Second', 'type' => 'Dayong', 'expected_amount' => 100]);
        $member = Member::create(['first_name' => 'Juan', 'last_name' => 'Cruz', 'council' => 'A', 'registration_date' => '2027-01-02', 'start_cycle_id' => $first->id]);
        $row = app(ComplianceReport::class)->rows()->first();
        $this->assertSame('Subject for Board expulsion review', $row['recommendation']);
        $this->assertSame('First, Second', $row['unpaid']);
        foreach ([$first, $second] as $cycle) {
            Payment::create(['member_id' => $member->id, 'collection_cycle_id' => $cycle->id, 'amount' => 100]);
        }
        $this->assertSame('Yes', app(ComplianceReport::class)->rows()->first()['standing']);
        $this->travelTo(now()->setDate(2028, 3, 2));
        $this->assertSame('Cycle missing', app(ComplianceReport::class)->rows()->first()['annual']);
        CollectionCycle::create(['name' => 'Annual 2028', 'type' => 'Annual Dues', 'expected_amount' => 100]);
        $this->assertSame('Review for Inactive status', app(ComplianceReport::class)->rows()->first()['recommendation']);
        $member->update(['member_status' => 'Deceased']);
        $this->assertSame('—', app(ComplianceReport::class)->rows()->first()['standing']);
    }

    public function test_csv_import_is_atomic_and_export_excludes_accounts(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $csv = "first_name,last_name,council\nJuan,Cruz,A\nJuan,Cruz,A\n";
        $this->post('/admin/data-tools/import-members', ['file' => UploadedFile::fake()->createWithContent('members.csv', $csv)])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('members', 0);
        $this->post('/admin/data-tools/import-members', ['file' => UploadedFile::fake()->createWithContent('members.csv', "first_name,last_name,council\nJuan,Cruz,A\n")])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('members', 1);
        $response = $this->post('/admin/data-tools/export', ['table' => 'members'])->assertOk();
        $this->assertStringContainsString('Juan', $response->streamedContent());
        $this->post('/admin/data-tools/export', ['table' => 'users'])->assertSessionHasErrors('table');
    }

    public function test_backup_download_contains_a_valid_database(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $response = $this->post('/admin/data-tools/backup')->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            $pdo = new \PDO('sqlite:'.$path);
            $this->assertSame('ok', $pdo->query('PRAGMA integrity_check')->fetchColumn());
            $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
            $pdo = null;
        } finally {
            unlink($path);
        }
    }
}
