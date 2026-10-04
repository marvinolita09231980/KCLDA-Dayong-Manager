<?php

namespace Tests\Feature;

use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Widgets\MemberPaymentStatusWidget;
use App\Models\CollectionCycle;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentReportExport;
use App\Services\PaymentCycleColumns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PaymentReportExportTest extends TestCase
{
    use RefreshDatabase;

    private function cycle(string $name): CollectionCycle
    {
        return CollectionCycle::create(['name' => $name, 'type' => 'Dayong', 'expected_amount' => 100]);
    }

    private function member(string $name, string $council = 'North', ?int $start = null): Member
    {
        return Member::create(['first_name' => $name, 'last_name' => 'Member', 'council' => $council, 'start_cycle_id' => $start]);
    }

    private function pay(Member $member, CollectionCycle $cycle, string $amount = '100.25', ?string $date = '2026-09-15'): Payment
    {
        return $member->payments()->create(['collection_cycle_id' => $cycle->id, 'amount' => $amount, 'date_paid' => $date]);
    }

    private function rows($page): array
    {
        $content = base64_decode($page->effects['download']['content']);
        $file = fopen('php://temp', 'r+');
        fwrite($file, substr($content, 3));
        rewind($file);
        $rows = [];
        while (($row = fgetcsv($file, null, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($file);

        return $rows;
    }

    public function test_paid_download_includes_all_filtered_members_and_only_selected_cycle_columns(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'legacy_permissions' => ['collections.view']]));
        $first = $this->cycle('First');
        $second = $this->cycle('=Second');
        $third = $this->cycle('Third');
        for ($i = 1; $i <= 12; $i++) {
            $member = $this->member('Export '.$i);
            $this->pay($member, $first, '20.10');
            $this->pay($member, $second);
            $this->pay($member, $third, '999');
        }
        $this->pay($this->member('Export South', 'South'), $second);
        $this->pay($this->member('Export Old'), $second, '888', '2026-08-31');
        $this->pay($this->member('Hidden'), $second);
        $this->pay($this->member('Export Undated'), $second, '888', null);
        $this->pay($this->member('Export Later', 'North', $third->id), $second);

        $page = Livewire::test(ListPayments::class)
            ->filterTable('collection_cycle_id', [$first->id, $second->id])
            ->filterTable('council', 'North')
            ->filterTable('payment_date', ['from' => '2026-09-15', 'to' => '2026-09-15'])
            ->searchTable('Export')
            ->assertCountTableRecords(24)
            ->callAction('downloadReport', ['cycle_ids' => [$second->id]])
            ->assertHasNoActionErrors()
            ->assertFileDownloaded('payments-report-'.now()->format('Y-m-d').'.csv');
        $rows = $this->rows($page);
        $this->assertCount(14, $rows);
        $this->assertSame(['Member', 'Council', "'=Second (PHP)", 'Amount to be collected (PHP)'], $rows[0]);
        $this->assertSame(['Export 1 Member', 'North', '100.25', '0.00'], $rows[1]);
        $this->assertSame(['Total (PHP)', '', '1203.00', '0.00'], $rows[13]);
    }

    public function test_unpaid_download_matches_multiple_cycles_dates_council_and_search(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $first = $this->cycle('First');
        $second = $this->cycle('Second');
        $third = $this->cycle('Third');
        $missing = $this->member('Export Missing');
        $partial = $this->member('Export Partial');
        $later = $this->member('Export Later', 'North', $second->id);
        $this->pay($partial, $first, '20.10');
        $this->pay($partial, $second, '0');
        $this->pay($later, $second, '100', '2026-08-31');
        $paid = $this->member('Export Paid');
        $this->pay($paid, $first);
        $this->pay($paid, $second);
        $future = $this->member('Export Future', 'North', $third->id);
        $south = $this->member('Export South', 'South');
        $hidden = $this->member('Hidden');

        $page = Livewire::test(MemberPaymentStatusWidget::class, ['sharedFilters' => [
            'collection_cycle_id' => ['values' => [$first->id, $second->id]],
            'council' => ['value' => 'North'],
            'payment_date' => ['from' => '2026-09-15', 'to' => '2026-09-15'],
        ]])->searchTable('Export')
            ->assertCanSeeTableRecords([$missing, $partial, $later])
            ->assertCanNotSeeTableRecords([$paid, $future, $south, $hidden])
            ->callTableAction('downloadReport', data: ['cycle_ids' => [$first->id, $second->id]])
            ->assertHasNoTableActionErrors()
            ->assertFileDownloaded('unpaid-report-'.now()->format('Y-m-d').'.csv');
        $rows = $this->rows($page);
        $this->assertCount(5, $rows);
        $this->assertSame(['Export Missing Member', 'North', '0.00', '0.00', '200.00'], $rows[1]);
        $this->assertSame(['Export Partial Member', 'North', '20.10', '0.00', '179.90'], $rows[2]);
        $this->assertSame(['Export Later Member', 'North', 'Not applicable', '100.00', '0.00'], $rows[3]);
        $this->assertSame(['Total (PHP)', '', '20.10', '100.00', '379.90'], $rows[4]);
    }

    public function test_download_rejects_empty_or_unavailable_cycle_columns(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $first = $this->cycle('First');
        $second = $this->cycle('Second');
        Livewire::test(ListPayments::class)
            ->filterTable('collection_cycle_id', [$first->id])
            ->callAction('downloadReport', ['cycle_ids' => []])
            ->assertHasActionErrors(['cycle_ids' => 'required'])
            ->assertNoFileDownloaded();
        Livewire::test(ListPayments::class)
            ->filterTable('collection_cycle_id', [$first->id])
            ->callAction('downloadReport', ['cycle_ids' => [$second->id + 1000]])
            ->assertHasActionErrors()->assertNoFileDownloaded();
    }

    public function test_report_columns_are_independent_of_the_filtered_paid_and_unpaid_member_lists(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $third = $this->cycle('3rd cycle');
        $registration = CollectionCycle::create(['name' => '2026 Registration', 'type' => 'Registration Fee', 'expected_amount' => 100]);
        $annual = CollectionCycle::create(['name' => '2026 Annual Dues', 'type' => 'Annual Dues', 'expected_amount' => 100]);
        $paid = $this->member('Paid');
        $unpaid = $this->member('Unpaid');
        $this->pay($paid, $third, '200', '2026-09-15')->update(['receipt_number' => 'MATCH-RECEIPT']);
        $this->pay($paid, $registration, '100', '2026-01-01');
        $this->pay($unpaid, $annual, '100', '2026-01-01');

        $page = Livewire::test(ListPayments::class)
            ->filterTable('collection_cycle_id', [$third->id])
            ->filterTable('payment_date', ['from' => '2026-09-01', 'to' => '2026-09-30'])
            ->searchTable('MATCH-RECEIPT')
            ->callAction('downloadReport', ['cycle_ids' => [$third->id, $registration->id]])
            ->assertHasNoActionErrors()->assertFileDownloaded();
        $rows = $this->rows($page);
        $this->assertCount(3, $rows);
        $this->assertSame(['Member', 'Council', '3rd cycle (PHP)', '2026 Annual Dues / Registration (PHP)', 'Amount to be collected (PHP)'], $rows[0]);
        $this->assertSame(['Paid Member', 'North', '200.00', '100.00', '0.00'], $rows[1]);

        $page = Livewire::test(MemberPaymentStatusWidget::class, ['sharedFilters' => [
            'collection_cycle_id' => ['values' => [$third->id]],
            'payment_date' => ['from' => '2026-09-01', 'to' => '2026-09-30'],
        ]])->assertCanSeeTableRecords([$unpaid])->assertCanNotSeeTableRecords([$paid])
            ->callTableAction('downloadReport', data: ['cycle_ids' => [$third->id, $registration->id]])
            ->assertHasNoTableActionErrors()->assertFileDownloaded();
        $rows = $this->rows($page);
        $this->assertCount(3, $rows);
        $this->assertSame(['Unpaid Member', 'North', '0.00', '100.00', '100.00'], $rows[1]);
    }

    public function test_registration_satisfies_same_year_annual_dues_in_both_tabs_but_not_next_year(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $registration = CollectionCycle::create(['name' => '2026 Registration', 'type' => 'Registration Fee', 'expected_amount' => 100]);
        $annual = CollectionCycle::create(['name' => '2026 Annual Dues', 'type' => 'Annual Dues', 'expected_amount' => 100]);
        $next = CollectionCycle::create(['name' => '2027 Annual Dues', 'type' => 'Annual Dues', 'expected_amount' => 100]);
        $dayong = $this->cycle('3rd cycle');
        $new = $this->member('New', 'North', $dayong->id);
        $new->update(['registration_date' => '2026-09-01']);
        $old = $this->member('Old');
        $missing = $this->member('Missing');
        $registrationPayment = $this->pay($new, $registration, '100');
        $annualPayment = $this->pay($old, $annual, '100');
        $this->assertSame(['2026 Annual Dues / Registration', '2027 Annual Dues / Registration', '3rd cycle'], PaymentCycleColumns::all()->pluck('name')->all());

        Livewire::test(ListPayments::class)->filterTable('collection_cycle_id', [$registration->id])
            ->assertCanSeeTableRecords([$registrationPayment, $annualPayment])->assertCountTableRecords(2);
        Livewire::test(MemberPaymentStatusWidget::class, ['sharedFilters' => ['collection_cycle_id' => ['values' => [$annual->id]]]])
            ->assertCanSeeTableRecords([$missing])->assertCanNotSeeTableRecords([$new, $old]);
        Livewire::test(MemberPaymentStatusWidget::class, ['sharedFilters' => ['collection_cycle_id' => ['values' => [$next->id]]]])
            ->assertCanSeeTableRecords([$new, $old, $missing]);
    }

    public function test_export_rechecks_permission(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'legacy_permissions' => []]));
        $this->expectException(HttpException::class);
        app(PaymentReportExport::class)->download(false, Payment::query(), [], []);
    }

    public function test_image_download_uses_filtered_paid_and_unpaid_report_data(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'legacy_permissions' => ['collections.view']]));
        $cycle = $this->cycle('Image cycle');
        $paid = $this->member('Image Paid');
        $this->pay($paid, $cycle, '100');
        $this->pay($this->member('Hidden', 'South'), $cycle, '100');
        $this->member('Image Unpaid');
        Livewire::test(ListPayments::class)->filterTable('council', 'North')
            ->callAction('downloadReport', ['cycle_ids' => [$cycle->id], 'format' => 'png'])
            ->assertHasNoActionErrors()
            ->assertDispatched('download-payment-report-images', fn ($event, $params) =>
                count($params['report']['rows']) === 1 && $params['report']['rows'][0][0] === 'Image Paid Member');
        Livewire::test(MemberPaymentStatusWidget::class, ['sharedFilters' => ['council' => ['value' => 'North']]])
            ->callTableAction('downloadReport', data: ['cycle_ids' => [$cycle->id], 'format' => 'png'])
            ->assertHasNoTableActionErrors()
            ->assertDispatched('download-payment-report-images', fn ($event, $params) =>
                count($params['report']['rows']) === 1 && $params['report']['rows'][0][0] === 'Image Unpaid Member');
    }

    public function test_amount_to_collect_counts_one_annual_charge_and_does_not_transfer_overpayments(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $first = $this->cycle('First');
        $second = $this->cycle('Second');
        $registration = CollectionCycle::create(['name' => '2026 Registration', 'type' => 'Registration Fee', 'expected_amount' => 100]);
        CollectionCycle::create(['name' => '2026 Annual Dues', 'type' => 'Annual Dues', 'expected_amount' => 100]);
        $member = $this->member('Balance');
        $this->pay($member, $first, '200');
        $this->pay($member, $second, '25.50');
        $this->pay($member, $registration, '100');
        $missing = $this->member('Missing');
        $report = app(PaymentReportExport::class)->report(true, Member::query(), [], [$first->id, $second->id, $registration->id]);
        $this->assertSame('74.50', $report['rows'][0][5]);
        $this->assertSame('300.00', $report['rows'][1][5]);
        $this->assertSame('374.50', $report['totals'][5]);
        $preview = view('reports.payment-report-preview', ['report' => $report])->render();
        $pdf = view('reports.payment-report-pdf', ['report' => $report])->render();
        foreach ([$preview, $pdf] as $html) {
            $this->assertStringContainsString('Amount to be collected (PHP)', $html);
            $this->assertStringContainsString('374.50', $html);
            $this->assertStringNotContainsString('Total paid in included cycles', $html);
        }
    }

    public function test_paid_report_has_preview_before_downloading_a_pdf(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'legacy_permissions' => ['collections.view']]));
        $cycle = $this->cycle('Preview cycle');
        $member = $this->member('Preview Ana');
        $this->pay($member, $cycle, '125.50');
        $this->pay($this->member('Excluded', 'South'), $cycle, '999');

        $page = Livewire::test(ListPayments::class)
            ->filterTable('council', 'North')
            ->mountAction('downloadReport')
            ->fillForm(['cycle_ids' => [$cycle->id], 'format' => 'pdf'])
            ->assertNoFileDownloaded();
        $preview = $page->instance()->{$page->instance()->getMountedActionSchemaName()}->toHtml();
        $this->assertStringContainsString('Report options', $preview);
        $this->assertStringContainsString('Preview Ana Member', $preview);
        $this->assertStringContainsString('125.50', $preview);
        $this->assertStringNotContainsString('Excluded Member', $preview);
        $page->callMountedAction()->assertHasNoActionErrors()
            ->assertFileDownloaded('payments-report-'.now()->format('Y-m-d').'.pdf', contentType: 'application/pdf');

        $this->assertStringStartsWith('%PDF-', base64_decode($page->effects['download']['content']));
    }

    public function test_unpaid_pdf_handles_wide_reports_and_empty_results(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $cycles = collect(range(1, 6))->map(fn ($number) => $this->cycle('Cycle '.$number));
        $this->member('Unpaid Ana');
        $page = Livewire::test(MemberPaymentStatusWidget::class)
            ->callTableAction('downloadReport', data: ['cycle_ids' => $cycles->pluck('id')->all(), 'format' => 'pdf'])
            ->assertHasNoTableActionErrors()
            ->assertFileDownloaded('unpaid-report-'.now()->format('Y-m-d').'.pdf', contentType: 'application/pdf');
        $pdf = base64_decode($page->effects['download']['content']);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertMatchesRegularExpression('/\/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+612(?:\.0+)?\s+936(?:\.0+)?\s*\]/', $pdf);
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b/', $pdf));

        $page = Livewire::test(MemberPaymentStatusWidget::class)
            ->searchTable('No matching member')
            ->mountTableAction('downloadReport')
            ->fillForm(['cycle_ids' => $cycles->pluck('id')->all(), 'format' => 'pdf'])
            ->assertNoFileDownloaded();
        $preview = $page->instance()->{$page->instance()->getMountedActionSchemaName()}->toHtml();
        $this->assertStringContainsString('No members match the selected filters.', $preview);
        $page->callMountedAction()->assertHasNoActionErrors()->assertFileDownloaded();
    }
}
