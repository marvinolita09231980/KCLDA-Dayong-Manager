<?php

namespace App\Services;

use App\Models\CollectionCycle;
use App\Models\Member;

class CollectionStatisticsReport
{
    public function generate(array $cycleIds = [], ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $cycles = CollectionCycle::query()->when($cycleIds !== [], fn ($query) => $query->whereIn('id', $cycleIds))->get();
        $zeroPayments = collect();
        $deceasedPayments = collect();
        $rows = Member::query()->select(['id', 'first_name', 'middle_name', 'last_name', 'council', 'start_cycle_id', 'registration_date', 'member_status', 'date_of_death'])
            ->with(['registrationPayments.collectionCycle', 'payments' => fn ($query) => $query
                ->whereIn('collection_cycle_id', $cycles->modelKeys())])
            ->get()
            ->filter(fn (Member $member) => $cycles->contains(fn ($cycle) => PaymentCycleColumns::cycleApplicable($member, $cycle)))
            ->groupBy(fn (Member $member) => $member->council ?: 'Unassigned')
            ->map(function ($members, $council) use ($cycles, $zeroPayments, $deceasedPayments, $dateFrom, $dateTo) {
                $row = ['council' => $council, 'paid' => 0, 'unpaid' => 0, 'payment_records' => 0, 'zero_payment_records' => 0, 'amount_cents' => 0];
                foreach ($members as $member) {
                    $payments = $member->payments->filter(fn ($payment) =>
                        (! $dateFrom || ($payment->date_paid && $payment->date_paid->toDateString() >= $dateFrom)) &&
                        (! $dateTo || ($payment->date_paid && $payment->date_paid->toDateString() <= $dateTo))
                    )->keyBy('collection_cycle_id');
                    $applicable = $cycles->filter(fn ($cycle) => PaymentCycleColumns::cycleApplicable($member, $cycle));
                    $historical = $applicable->filter(function ($cycle) use ($member) {
                        if (! $member->isDeceased()) {
                            return true;
                        }
                        // Recorded participation remains historical even when the cycle has no dates.
                        if ($member->payments->contains(fn ($payment) => $payment->collection_cycle_id === $cycle->id && (float) $payment->amount > 0)) {
                            return true;
                        }

                        return $member->date_of_death && $cycle->start_date
                            && $cycle->start_date->lte($member->date_of_death);
                    });
                    $unpaid = $historical->contains(fn ($cycle) => (float) ($payments->get($cycle->id)?->amount ?? 0) <= 0);
                    if ($historical->isNotEmpty()) {
                        $row[$unpaid ? 'unpaid' : 'paid']++;
                        if (! $unpaid && $member->isDeceased()) {
                            $deceasedPayments->push($member->id);
                        }
                    }
                    foreach ($applicable as $cycle) {
                        $payment = $payments->get($cycle->id);
                        if (! $payment) {
                            continue;
                        }
                        $row['payment_records']++;
                        if ((float) $payment->amount <= 0) {
                            $row['zero_payment_records']++;
                            $zeroPayments->push([
                                'id' => $payment->id,
                                'member' => $member->full_name,
                                'council' => $council,
                                'cycle' => $cycle->name,
                                'receipt' => $payment->receipt_number,
                            ]);
                        }
                        $row['amount_cents'] += (int) round((float) $payment->amount * 100);
                    }
                }

                return $row;
            })->sortBy('council', SORT_NATURAL | SORT_FLAG_CASE)->values();

        return [
            'rows' => $rows,
            'totals' => ['paid' => $rows->sum('paid'), 'unpaid' => $rows->sum('unpaid'), 'payment_records' => $rows->sum('payment_records'), 'zero_payment_records' => $rows->sum('zero_payment_records'), 'amount_cents' => $rows->sum('amount_cents')],
            'zeroPayments' => $zeroPayments,
            'deceasedPaidMembers' => $deceasedPayments->unique()->count(),
        ];
    }
}
