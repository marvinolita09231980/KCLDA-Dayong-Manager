<?php

namespace App\Services;

use App\Models\Disbursement;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class DayongFinancialPeriod
{
    public function currentClosure(): ?Disbursement
    {
        return Disbursement::query()->where('closes_financial_period', true)
            ->orderByDesc('disbursement_date')->orderByDesc('id')->first();
    }

    public function canClose(Disbursement $claim): bool
    {
        if ($claim->category !== 'Claims' || $claim->closes_financial_period) {
            return false;
        }

        $latest = $this->currentClosure();

        return ! $latest || $claim->disbursement_date->gt($latest->disbursement_date);
    }

    public function close(Disbursement $claim): void
    {
        abort_unless($this->canClose($claim), 422, 'Only a Claims disbursement after the latest closure can close the financial period.');
        $closedAt = now();
        $closingBalanceCents = $this->balanceThrough($claim->disbursement_date, $closedAt);

        $claim->update([
            'closes_financial_period' => true,
            'closing_balance' => $closingBalanceCents / 100,
            'period_closed_at' => $closedAt,
        ]);
    }

    public function position(): array
    {
        $closure = $this->currentClosure();
        $periodStart = $closure?->disbursement_date?->copy()->addDay();
        $beginningBalanceCents = $closure
            ? (int) round((float) $closure->closing_balance * 100)
            : (int) round((float) config('dayong.initial_bank_balance', 2000) * 100);

        $payments = Payment::query();
        $disbursements = Disbursement::query();
        if ($closure) {
            $this->afterClosurePayments($payments, $closure);
            $disbursements->whereDate('disbursement_date', '>', $closure->disbursement_date);
        }

        $collectionCents = $this->sumCents($payments);
        $disbursementCents = $this->sumCents($disbursements);
        $totalFundsCents = $beginningBalanceCents + $collectionCents;
        $disbursementItems = (clone $disbursements)
            ->selectRaw("COALESCE(NULLIF(TRIM(category), ''), 'Others') as category, COUNT(*) as record_count, SUM(ROUND(amount * 100)) as cents")
            ->groupByRaw("COALESCE(NULLIF(TRIM(category), ''), 'Others')")
            ->orderBy('category')->get()->map(fn ($item) => [
                'category' => $item->category,
                'recordCount' => (int) $item->record_count,
                'cents' => (int) $item->cents,
            ]);

        $outstandingPayables = \App\Models\Payable::whereNull('disbursement_id')
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')->orderBy('due_date')->orderBy('id')->get();
        $outstandingPayablesCents = (int) $outstandingPayables->sum(fn ($payable) => (int) round((float) $payable->amount * 100));

        return compact('closure', 'periodStart', 'beginningBalanceCents', 'collectionCents', 'totalFundsCents', 'disbursementCents', 'disbursementItems', 'outstandingPayablesCents', 'outstandingPayables') + [
            'availableBalanceCents' => $totalFundsCents - $disbursementCents - $outstandingPayablesCents,
            'currentDayongBalanceCents' => $totalFundsCents - $disbursementCents,
            'ledger' => (clone $disbursements)->orderBy('disbursement_date')->orderBy('id')->get(),
        ];
    }

    public function balanceOn(Carbon $date): int
    {
        $closure = Disbursement::query()->where('closes_financial_period', true)
            ->whereDate('disbursement_date', '<=', $date)
            ->orderByDesc('disbursement_date')->orderByDesc('id')->first();
        $beginningBalanceCents = $closure
            ? (int) round((float) $closure->closing_balance * 100)
            : (int) round((float) config('dayong.initial_bank_balance', 2000) * 100);
        $payments = Payment::query()->where(function (Builder $query) use ($date): void {
            $query->whereDate('date_paid', '<=', $date)
                ->orWhere(fn (Builder $query) => $query->whereNull('date_paid')->where('created_at', '<=', $date->copy()->endOfDay()));
        });
        $disbursements = Disbursement::query()->whereDate('disbursement_date', '<=', $date);
        if ($closure) {
            $this->afterClosurePayments($payments, $closure);
            $disbursements->whereDate('disbursement_date', '>', $closure->disbursement_date);
        }

        return $beginningBalanceCents + $this->sumCents($payments) - $this->sumCents($disbursements);
    }

    private function balanceThrough(Carbon $date, Carbon $closedAt): int
    {
        $payments = Payment::query()->where(function (Builder $query) use ($date, $closedAt): void {
            $query->whereDate('date_paid', '<=', $date)
                ->orWhere(fn (Builder $query) => $query->whereNull('date_paid')->where('created_at', '<=', $closedAt));
        });
        $disbursements = Disbursement::query()->whereDate('disbursement_date', '<=', $date);
        $initialBalance = (int) round((float) config('dayong.initial_bank_balance', 2000) * 100);

        return $initialBalance + $this->sumCents($payments) - $this->sumCents($disbursements);
    }

    private function afterClosurePayments(Builder $query, Disbursement $closure): void
    {
        $query->where(function (Builder $query) use ($closure): void {
            $query->whereDate('date_paid', '>', $closure->disbursement_date)
                ->orWhere(fn (Builder $query) => $query->whereNull('date_paid')->where('created_at', '>', $closure->period_closed_at));
        });
    }

    private function sumCents(Builder $query): int
    {
        return (int) (clone $query)->selectRaw('COALESCE(SUM(ROUND(amount * 100)), 0) as cents')->value('cents');
    }
}
