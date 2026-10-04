<?php

namespace Tests\Feature;

use App\Filament\Pages\FinancialReport;
use App\Filament\Resources\Payables\Pages\ListPayables;
use App\Filament\Resources\Payables\PayableResource;
use App\Models\Disbursement;
use App\Models\Payable;
use App\Models\User;
use App\Services\DayongFinancialPeriod;
use App\Services\PayOutstandingPayable;
use App\Services\YearRangeFinancialReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PayablesTest extends TestCase
{
    use RefreshDatabase;

    private function payable(): Payable
    {
        return Payable::create(['payee' => 'Florist', 'category' => 'Necrological service', 'particulars' => 'Funeral wreath', 'amount' => 2500]);
    }

    public function test_payment_moves_the_commitment_to_cash_expense_exactly_once(): void
    {
        config(['dayong.initial_bank_balance' => 10000]);
        $admin = User::factory()->create(['active' => true, 'is_admin' => true]);
        $this->actingAs($admin);
        $payable = $this->payable();
        $position = app(DayongFinancialPeriod::class)->position();
        $this->assertSame(1000000, $position['currentDayongBalanceCents']);
        $this->assertSame(250000, $position['outstandingPayablesCents']);
        $this->assertSame(750000, $position['availableBalanceCents']);
        $this->assertDatabaseCount('disbursements', 0);

        Livewire::test(FinancialReport::class)->assertSee('Outstanding payables')->assertSee('7,500.00');
        Livewire::test(ListPayables::class)->assertCanSeeTableRecords([$payable])
            ->callTableAction('pay', $payable, ['disbursement_date' => today()->toDateString(), 'voucher_number' => 'W-1'])
            ->assertHasNoTableActionErrors();
        $payment = app(PayOutstandingPayable::class)->pay($payable, ['disbursement_date' => today()->toDateString(), 'voucher_number' => 'W-1'], $admin);
        $this->assertDatabaseCount('disbursements', 1);
        $this->assertSame($payment->id, $payable->fresh()->disbursement_id);
        $position = app(DayongFinancialPeriod::class)->position();
        $this->assertSame(750000, $position['currentDayongBalanceCents']);
        $this->assertSame(0, $position['outstandingPayablesCents']);
        $this->assertSame(750000, $position['availableBalanceCents']);
        $this->assertFalse(PayableResource::canEdit($payable->fresh()));
        $this->assertFalse(PayableResource::canDelete($payable->fresh()));
        Livewire::test(ListPayables::class)->filterTable('status', 'paid')->assertCanSeeTableRecords([$payable]);
    }

    public function test_create_edit_delete_and_amount_validation(): void
    {
        $this->actingAs(User::factory()->create(['active' => true, 'is_admin' => true]));
        $data = ['payee' => 'Florist', 'category' => 'Necrological service', 'particulars' => 'Wreath', 'amount' => 0];
        Livewire::test(ListPayables::class)->callAction('create', $data)->assertHasActionErrors(['amount']);
        $data['amount'] = 2500;
        Livewire::test(ListPayables::class)->callAction('create', $data)->assertHasNoActionErrors();
        $payable = Payable::firstOrFail();
        Livewire::test(ListPayables::class)->callTableAction('edit', $payable, ['amount' => 2400])->assertHasNoTableActionErrors();
        $this->assertSame('2400.00', $payable->fresh()->amount);
        Livewire::test(ListPayables::class)->callTableAction('delete', $payable);
        $this->assertDatabaseCount('payables', 0);
        $this->assertDatabaseCount('disbursements', 0);
    }

    public function test_viewer_cannot_pay(): void
    {
        $viewer = User::factory()->create(['active' => true, 'is_admin' => false, 'legacy_permissions' => ['disbursements.view']]);
        $this->actingAs($viewer);
        $payable = $this->payable();
        Livewire::test(ListPayables::class)->assertTableActionHidden('pay', $payable)->assertTableActionHidden('edit', $payable);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(PayOutstandingPayable::class)->pay($payable, ['disbursement_date' => today()->toDateString(), 'voucher_number' => 'W-1'], $viewer);
    }

    public function test_outstanding_payables_carry_forward_and_payment_cannot_be_backdated_into_a_closed_period(): void
    {
        config(['dayong.initial_bank_balance' => 10000]);
        $admin = User::factory()->create(['active' => true, 'is_admin' => true]);
        $payable = $this->payable();
        $claim = Disbursement::create(['disbursement_date' => '2026-01-01', 'category' => 'Claims', 'amount' => 1000]);
        app(DayongFinancialPeriod::class)->close($claim);
        $position = app(DayongFinancialPeriod::class)->position();
        $this->assertSame(900000, $position['currentDayongBalanceCents']);
        $this->assertSame(650000, $position['availableBalanceCents']);
        try {
            app(PayOutstandingPayable::class)->pay($payable, ['disbursement_date' => '2026-01-01', 'voucher_number' => 'W-1'], $admin);
            $this->fail('Closed-period payment must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('disbursement_date', $exception->errors());
        }
        $this->assertDatabaseCount('disbursements', 1);
        $this->assertNull($payable->fresh()->disbursement_id);
    }

    public function test_paid_records_cannot_be_changed_or_deleted(): void
    {
        $admin = User::factory()->create(['active' => true, 'is_admin' => true]);
        $payable = $this->payable();
        $payment = app(PayOutstandingPayable::class)->pay($payable, ['disbursement_date' => today()->toDateString(), 'voucher_number' => 'W-1'], $admin);
        foreach ([fn () => $payment->update(['amount' => 1]), fn () => $payment->delete(), fn () => $payable->update(['amount' => 1]), fn () => $payable->delete()] as $operation) {
            try {
                $operation();
                $this->fail('Settled records must be protected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('amount', $exception->errors());
            }
        }
        $this->assertSame('2500.00', $payment->fresh()->amount);
        $this->assertSame('2500.00', $payable->fresh()->amount);
    }

    public function test_year_report_carries_unpaid_payables_and_excludes_paid_ones(): void
    {
        config(['dayong.initial_bank_balance' => 10000]);
        $admin = User::factory()->create(['active' => true, 'is_admin' => true]);
        $this->travelTo(now()->setDate(2024, 12, 15));
        Payable::create(['payee' => 'Carry', 'category' => 'Others', 'particulars' => 'Prior unpaid item', 'amount' => 50]);
        $this->travelTo(now()->setDate(2025, 2, 2));
        $paid = Payable::create(['payee' => 'Florist', 'category' => 'Others', 'particulars' => 'Wreath', 'amount' => 100]);
        app(PayOutstandingPayable::class)->pay($paid, ['disbursement_date' => '2025-02-02', 'voucher_number' => 'W-1'], $admin);

        $report = app(YearRangeFinancialReport::class)->forYears(2025, 2025);
        $this->assertSame(1000000, $report['openingBalanceCents']);
        $this->assertSame(10000, $report['disbursementCents']);
        $this->assertSame(990000, $report['endingBalanceCents']);
        $this->assertSame(5000, $report['outstandingPayablesCents']);
        $this->assertSame(['Carry'], $report['outstandingPayables']->pluck('payee')->all());
        $this->assertSame(985000, $report['availableBalanceCents']);
    }
}
