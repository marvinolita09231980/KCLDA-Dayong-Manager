<?php

namespace Tests\Feature;

use App\Models\CollectionCycle;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use App\Services\ComplianceReport;
use App\Services\ComplianceCouncilPdf;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ComplianceAndToolsTest extends TestCase
{
    use DatabaseMigrations;

    public function test_registration_and_annual_dues_timeline_starts_in_2026(): void
    {
        $this->travelTo(now()->setDate(2025, 12, 15));
        $existing = Member::create(['first_name' => 'Existing', 'last_name' => 'Member', 'council' => 'A', 'member_status' => 'Active', 'registration_date' => '2025-05-01']);
        $this->assertSame('Starts 2026', app(ComplianceReport::class)->rows()->first()['annual']);

        $this->travelTo(now()->setDate(2026, 3, 2));
        $registration = CollectionCycle::create(['name' => 'Registration 2026', 'type' => 'Registration Fee', 'expected_amount' => 100]);
        $annual = CollectionCycle::create(['name' => 'Annual 2026', 'type' => 'Annual Dues', 'expected_amount' => 100]);
        $new = Member::create(['first_name' => 'New', 'last_name' => 'Member', 'council' => 'A', 'member_status' => 'Active', 'registration_date' => '2026-01-10']);
        Payment::create(['member_id' => $new->id, 'collection_cycle_id' => $registration->id, 'amount' => 100]);

        $rows = app(ComplianceReport::class)->rows()->keyBy('id');
        $this->assertSame('Annual 2026', $rows[$existing->id]['annual']);
        $this->assertSame('No', $rows[$existing->id]['standing']);
        $this->assertSame('Review for Inactive status', $rows[$existing->id]['recommendation']);
        $this->assertSame('Registration covers 2026', $rows[$new->id]['annual']);
        $this->assertSame('Yes', $rows[$new->id]['standing']);
        Payment::create(['member_id' => $existing->id, 'collection_cycle_id' => $annual->id, 'amount' => 100]);
        $this->assertSame('Yes', app(ComplianceReport::class)->rows()->keyBy('id')[$existing->id]['standing']);

        $this->travelTo(now()->setDate(2027, 3, 2));
        CollectionCycle::create(['name' => 'Annual 2027', 'type' => 'Annual Dues', 'expected_amount' => 100]);
        $this->assertSame('No', app(ComplianceReport::class)->rows()->keyBy('id')[$new->id]['standing']);
    }

    public function test_payment_details_combine_same_year_fees_and_preserve_payment_information(): void
    {
        $member = Member::create(['first_name' => 'New', 'last_name' => 'Member', 'council' => 'A', 'registration_date' => '2026-01-01']);
        $registration = CollectionCycle::create(['name' => 'CY 2026 Registration Fee', 'type' => 'Registration Fee', 'expected_amount' => 100]);
        $annual = CollectionCycle::create(['name' => 'CY 2026 Annual Dues (Optional)', 'type' => 'Annual Dues', 'expected_amount' => 100]);
        CollectionCycle::create(['name' => 'Mortuary cycle', 'type' => 'Dayong', 'expected_amount' => 200]);
        CollectionCycle::create(['name' => 'CY 2025 Registration Fee', 'type' => 'Registration Fee', 'expected_amount' => 100]);
        Payment::create(['member_id' => $member->id, 'collection_cycle_id' => $registration->id, 'amount' => 60, 'date_paid' => '2026-01-01', 'receipt_number' => 'REG-1', 'notes' => 'Registration payment']);
        Payment::create(['member_id' => $member->id, 'collection_cycle_id' => $annual->id, 'amount' => 40, 'date_paid' => '2026-02-01', 'receipt_number' => 'ANN-1', 'notes' => 'Balance payment']);

        $details = app(ComplianceReport::class)->rows()->first()['payment_cycles'];
        $this->assertCount(3, $details);
        $combined = $details->first();
        $this->assertSame('2026 Annual Dues / Registration', $combined['name']);
        $this->assertEquals(100, $combined['expected']);
        $this->assertEquals(100, $combined['paid']);
        $this->assertSame('Partial payment', $combined['status']);
        $this->assertSame('Registration Fee', $combined['type']);
        $this->assertSame('Jan 1, 2026; Feb 1, 2026', $combined['date']);
        $this->assertSame('REG-1; ANN-1', $combined['receipt']);
        $this->assertSame('Registration payment; Balance payment', $combined['notes']);
        $this->assertEquals(100, $details->sum('paid'));
    }

    public function test_registration_replaces_annual_dues_only_in_the_joining_year(): void
    {
        $this->travelTo(now()->setDate(2027, 3, 2));
        $registration = CollectionCycle::create(['name' => 'Registration 2027', 'type' => 'Registration Fee', 'expected_amount' => 500]);
        $annual = CollectionCycle::create(['name' => 'Annual dues', 'type' => 'Annual Dues', 'start_date' => '2027-01-01', 'expected_amount' => 100]);
        $new = Member::create(['first_name' => 'New', 'last_name' => 'Member', 'council' => 'A', 'member_status' => 'Active', 'registration_date' => '2027-01-02']);
        $imported = Member::create(['first_name' => 'Imported', 'last_name' => 'Member', 'council' => 'A', 'member_status' => 'Active']);
        $existing = Member::create(['first_name' => 'Existing', 'last_name' => 'Member', 'council' => 'A', 'member_status' => 'Active', 'registration_date' => '2026-01-02']);
        foreach ([$new, $imported] as $member) {
            Payment::create(['member_id' => $member->id, 'collection_cycle_id' => $registration->id, 'amount' => 500]);
        }

        $rows = app(ComplianceReport::class)->rows()->keyBy('id');
        foreach ([$new, $imported] as $member) {
            $this->assertSame('Yes', $rows[$member->id]['standing']);
            $this->assertSame('No action', $rows[$member->id]['recommendation']);
            $this->assertSame('Registration covers 2027', $rows[$member->id]['annual']);
            $this->assertCount(1, $rows[$member->id]['payment_cycles']);
            $this->assertSame('Paid', $rows[$member->id]['payment_cycles']->first()['status']);
            $this->assertEquals(500, $rows[$member->id]['payment_cycles']->first()['expected']);
        }
        $this->assertSame('Review for Inactive status', $rows[$existing->id]['recommendation']);
        Payment::create(['member_id' => $existing->id, 'collection_cycle_id' => $annual->id, 'amount' => 100]);
        $this->assertSame('Yes', app(ComplianceReport::class)->rows()->keyBy('id')[$existing->id]['standing']);

        $this->travelTo(now()->setDate(2028, 3, 2));
        CollectionCycle::create(['name' => 'Annual 2028', 'type' => 'Annual Dues', 'expected_amount' => 100]);
        $rows = app(ComplianceReport::class)->rows()->keyBy('id');
        foreach ([$new, $imported] as $member) {
            $this->assertSame('No', $rows[$member->id]['standing']);
            $this->assertSame('Review for Inactive status', $rows[$member->id]['recommendation']);
        }
    }

    public function test_needs_review_filter_includes_both_review_types_and_can_be_reset(): void
    {
        $this->travelTo(now()->setDate(2027, 3, 2));
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $annual = CollectionCycle::create(['name' => 'Annual 2027', 'type' => 'Annual Dues', 'expected_amount' => 100]);
        $first = CollectionCycle::create(['name' => 'First', 'type' => 'Dayong', 'expected_amount' => 100]);
        $second = CollectionCycle::create(['name' => 'Second', 'type' => 'Dayong', 'expected_amount' => 100]);
        $board = Member::create(['first_name' => 'Board', 'last_name' => 'Review', 'council' => 'A', 'member_status' => 'Active']);
        $inactive = Member::create(['first_name' => 'Inactive', 'last_name' => 'Review', 'council' => 'B', 'member_status' => 'Active']);
        $good = Member::create(['first_name' => 'Paid', 'last_name' => 'Member', 'council' => 'A', 'member_status' => 'Active']);
        $deceased = Member::create(['first_name' => 'Deceased', 'last_name' => 'Member', 'council' => 'A', 'member_status' => 'Deceased']);
        foreach ([$inactive, $good] as $member) {
            foreach ([$first, $second] as $cycle) {
                Payment::create(['member_id' => $member->id, 'collection_cycle_id' => $cycle->id, 'amount' => 100]);
            }
        }
        Payment::create(['member_id' => $good->id, 'collection_cycle_id' => $annual->id, 'amount' => 100]);

        \Livewire\Livewire::test(\App\Filament\Pages\Compliance::class)
            ->assertSeeHtml('<option value="Needs review">Needs review</option>')
            ->set('recommendation', 'Needs review')
            ->assertViewHas('rows', fn ($rows) => $rows->pluck('id')->sort()->values()->all() === [$board->id, $inactive->id])
            ->set('council', 'B')
            ->assertViewHas('rows', fn ($rows) => $rows->pluck('id')->all() === [$inactive->id])
            ->set('search', 'Nobody')
            ->assertViewHas('rows', fn ($rows) => $rows->isEmpty())
            ->call('resetFilters')
            ->assertSet('recommendation', '')
            ->assertViewHas('rows', fn ($rows) => $rows->count() === 4)
            ->set('recommendation', 'Subject for Board expulsion review')
            ->assertViewHas('rows', fn ($rows) => $rows->pluck('id')->all() === [$board->id]);
    }

    public function test_not_in_good_standing_without_review_filter(): void
    {
        $this->travelTo(now()->setDate(2027, 3, 2));
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $annual = CollectionCycle::create(['name' => 'Annual 2027', 'type' => 'Annual Dues', 'expected_amount' => 100]);
        $first = CollectionCycle::create(['name' => 'First', 'type' => 'Dayong', 'expected_amount' => 100]);
        $second = CollectionCycle::create(['name' => 'Second', 'type' => 'Dayong', 'expected_amount' => 100]);
        $members = collect([
            ['One unpaid', 'A', 'Active', [$annual, $first]],
            ['Recorded inactive', 'B', 'Inactive', [$annual, $first, $second]],
            ['Good standing', 'A', 'Active', [$annual, $first, $second]],
            ['Board review', 'A', 'Active', [$annual]],
            ['Inactive review', 'A', 'Active', [$first, $second]],
            ['Deceased', 'A', 'Deceased', []],
        ])->map(function ($data) {
            [$name, $council, $status, $cycles] = $data;
            $member = Member::create(['first_name' => $name, 'last_name' => 'Member', 'council' => $council, 'member_status' => $status]);
            foreach ($cycles as $cycle) {
                Payment::create(['member_id' => $member->id, 'collection_cycle_id' => $cycle->id, 'amount' => 100]);
            }

            return $member;
        });

        \Livewire\Livewire::test(\App\Filament\Pages\Compliance::class)
            ->assertSeeHtml('<option value="Not in good standing - but not subject for review">Not in good standing - but not subject for review</option>')
            ->set('recommendation', 'Not in good standing - but not subject for review')
            ->assertViewHas('rows', fn ($rows) => $rows->pluck('id')->sort()->values()->all() === $members->take(2)->pluck('id')->all())
            ->set('council', 'A')
            ->assertViewHas('rows', fn ($rows) => $rows->pluck('id')->all() === [$members[0]->id])
            ->set('search', 'Nobody')
            ->assertViewHas('rows', fn ($rows) => $rows->isEmpty())
            ->call('resetFilters')
            ->assertSet('recommendation', '')
            ->assertViewHas('rows', fn ($rows) => $rows->count() === 6);
    }

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

    public function test_council_pdf_reports_all_members_reasons_recommendations_and_section_references(): void
    {
        $this->travelTo(now()->setDate(2027, 3, 2));
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $annual = CollectionCycle::create(['name' => 'Annual 2027', 'type' => 'Annual Dues', 'expected_amount' => 100]);
        $first = CollectionCycle::create(['name' => 'Dayong first', 'type' => 'Dayong', 'expected_amount' => 100]);
        $second = CollectionCycle::create(['name' => 'Dayong second', 'type' => 'Dayong', 'expected_amount' => 100]);
        $review = Member::create(['first_name' => 'Needs', 'last_name' => 'Review', 'council' => 'A', 'member_status' => 'Active']);
        $good = Member::create(['first_name' => 'Good', 'last_name' => 'Member', 'council' => 'A', 'member_status' => 'Active']);
        Member::create(['first_name' => 'Other', 'last_name' => 'Council', 'council' => 'B', 'member_status' => 'Active']);
        foreach ([$annual, $first, $second] as $cycle) {
            Payment::create(['member_id' => $good->id, 'collection_cycle_id' => $cycle->id, 'amount' => 100]);
        }

        $service = app(ComplianceCouncilPdf::class);
        $report = $service->report('A');
        $this->assertSame(2, $report['total']);
        $this->assertSame(1, $report['good']);
        $this->assertSame(1, $report['review']);
        $this->assertSame(['Good Member', 'Needs Review'], $report['rows']->pluck('name')->all());
        $reviewRow = $report['rows']->firstWhere('id', $review->id);
        $this->assertSame(10000, $reviewRow['annual_due_cents']);
        $this->assertSame(20000, $reviewRow['mortuary_due_cents']);
        $this->assertSame('Subject for Board expulsion review', $reviewRow['recommendation']);
        $this->assertSame(['Section 10', 'Section 4D', 'Section 4E'], $reviewRow['bylaw_references']);
        $this->assertTrue($reviewRow['board_reference_unverified']);

        $html = $service->html($report);
        $this->assertStringContainsString('@page { size: 8.5in 13in; margin: 0.25in; }', $html);
        $this->assertStringContainsString('Board review section to confirm', $html);
        $this->assertStringNotContainsString('Other Council', $html);
        $page = \Livewire\Livewire::test(\App\Filament\Pages\Compliance::class)
            ->set('council', 'A')
            ->set('search', 'Needs')
            ->call('downloadCouncilReport')
            ->assertFileDownloaded('good-standing-compliance-council-a-2027-03-02.pdf', contentType: 'application/pdf');
        $pdf = base64_decode($page->effects['download']['content']);
        $this->assertMatchesRegularExpression('/\/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+612(?:\.0+)?\s+936(?:\.0+)?\s*\]/', $pdf);

        $all = $service->reportAll();
        $this->assertSame(2, $all['councilCount']);
        $this->assertSame(3, $all['total']);
        $this->assertSame(['A', 'B'], $all['sections']->pluck('council')->all());
        $this->assertSame([2, 1], $all['sections']->pluck('total')->all());
        $allHtml = $service->html($all);
        $this->assertStringContainsString('Council A', $allHtml);
        $this->assertStringContainsString('Council B', $allHtml);
        $this->assertStringContainsString('page-break-before: always', $allHtml);
        \Livewire\Livewire::test(\App\Filament\Pages\Compliance::class)
            ->set('council', 'A')
            ->set('search', 'Needs')
            ->call('downloadAllCouncilReports')
            ->assertFileDownloaded('good-standing-compliance-all-councils-2027-03-02.pdf', contentType: 'application/pdf');
    }

    public function test_council_pdf_requires_compliance_permission(): void
    {
        Member::create(['first_name' => 'Private', 'last_name' => 'Member', 'council' => 'A']);
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => false, 'legacy_permissions' => ['collections.view']]));

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(ComplianceCouncilPdf::class)->report('A');
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
