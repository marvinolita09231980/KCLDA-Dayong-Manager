<?php

namespace App\Services;

use App\Models\Disbursement;
use App\Models\Payable;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class YearRangeFinancialReport
{
    public function __construct(private readonly DayongFinancialPeriod $financialPeriod) {}

    public function forYears(int $startYear, int $endYear): array
    {
        $start = Carbon::create($startYear, 1, 1)->startOfDay();
        $end = Carbon::create($endYear, 12, 31)->endOfDay();
        if ($end->isFuture()) {
            $end = now();
        }

        $openingDate = $start->copy()->subDay();
        $openingBalanceCents = $this->financialPeriod->balanceOn($openingDate);
        $payments = Payment::query()->where(function (Builder $query) use ($start, $end): void {
            $query->where(fn (Builder $query) => $query
                ->whereDate('date_paid', '>=', $start)
                ->whereDate('date_paid', '<=', $end))
                ->orWhere(fn (Builder $query) => $query->whereNull('date_paid')
                    ->whereBetween('created_at', [$start, $end]));
        });
        $disbursements = Disbursement::query()
            ->whereDate('disbursement_date', '>=', $start)
            ->whereDate('disbursement_date', '<=', $end);
        $collectionCents = $this->sumCents($payments);
        $paymentItems = (clone $payments)->with('collectionCycle:id,name')
            ->get(['id', 'collection_cycle_id', 'amount'])
            ->groupBy(fn (Payment $payment) => $payment->collectionCycle?->name ?? 'Unknown cycle')
            ->map(fn ($items, $cycle) => [
                'cycle' => $cycle,
                'records' => $items->count(),
                'cents' => (int) $items->sum(fn (Payment $payment) => (int) round((float) $payment->amount * 100)),
            ])->sortBy('cycle')->values();
        $disbursementCents = $this->sumCents($disbursements);
        $endingBalanceCents = $openingBalanceCents + $collectionCents - $disbursementCents;
        $outstandingPayables = Payable::query()->whereNull('disbursement_id')
            ->where('created_at', '<=', $end)
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')->orderBy('id')->get();
        $outstandingPayablesCents = (int) $outstandingPayables
            ->sum(fn (Payable $payable) => (int) round((float) $payable->amount * 100));

        return compact('startYear', 'endYear', 'openingDate', 'openingBalanceCents', 'collectionCents', 'disbursementCents', 'endingBalanceCents', 'outstandingPayables', 'outstandingPayablesCents') + [
            'throughDate' => $end,
            'paymentItems' => $paymentItems,
            'availableBalanceCents' => $endingBalanceCents - $outstandingPayablesCents,
            'disbursementItems' => (clone $disbursements)->orderBy('disbursement_date')->orderBy('id')->get(),
        ];
    }

    private function sumCents(Builder $query): int
    {
        return (int) (clone $query)->selectRaw('COALESCE(SUM(ROUND(amount * 100)), 0) as cents')->value('cents');
    }
}
