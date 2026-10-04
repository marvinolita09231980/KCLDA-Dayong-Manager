<?php

namespace App\Services;

use App\Models\CollectionCycle;
use App\Models\Member;
use Illuminate\Support\Collection;

class ComplianceReport
{
    private const FIRST_COMPLIANCE_YEAR = 2026;

    public function rows(?string $council = null): Collection
    {
        $cycles = CollectionCycle::orderBy('id')->get();
        $columns = PaymentCycleColumns::all();

        return Member::query()->with(['payments', 'registrationPayments.collectionCycle'])
            ->when($council !== null, fn ($query) => $query->where('council', $council))
            ->orderBy('council')->orderBy('last_name')->get()->map(function (Member $member) use ($cycles, $columns) {
            $row = ['id' => $member->id, 'name' => $member->full_name, 'council' => $member->council, 'status' => $member->isDeceased() ? 'Deceased' : $member->member_status,
                'registered' => $member->registration_date?->format('M j, Y') ?? 'Not recorded'];
            $registered = PaymentCycleColumns::registrationYear($member);
            $row['payment_cycles'] = $columns->values()->map(function ($column) use ($member, $registered) {
                $records = $member->payments->whereIn('collection_cycle_id', $column['ids']);
                $amount = PaymentCycleColumns::cents($member, $column) / 100;
                $charge = PaymentCycleColumns::charge($member, $column);
                $cycle = $charge ?? $column['cycles']->first();
                $credited = PaymentCycleColumns::creditedCents($member, $column) / 100;

                return [
                    'name' => $column['name'],
                    'type' => $charge?->type ?? 'Not applicable',
                    'expected' => (float) $cycle->expected_amount,
                    'paid' => $amount,
                    'date' => $records->map(fn ($payment) => $payment->date_paid?->format('M j, Y'))->filter()->join('; '),
                    'receipt' => $records->pluck('receipt_number')->filter()->join('; '),
                    'notes' => $records->pluck('notes')->filter()->join('; '),
                    'status' => ! $charge ? 'Not applicable' : ($records->isEmpty() ? 'No payment recorded' : ($credited >= (float) $charge->expected_amount ? 'Paid' : ($credited > 0 ? 'Partial payment' : 'Unpaid'))),
                ];
            });
            if ($member->isDeceased()) {
                return $row + ['annual' => '—', 'missed' => 0, 'unpaid' => '—', 'standing' => '—', 'recommendation' => '', 'reason' => 'Deceased', 'report_reason' => 'Recorded deceased.',
                    'annual_due_cents' => 0, 'mortuary_due_cents' => 0, 'bylaw_references' => [], 'board_reference_unverified' => false];
            }
            $year = now()->year;
            $firstYear = $registered >= self::FIRST_COMPLIANCE_YEAR ? $registered + 1 : self::FIRST_COMPLIANCE_YEAR;
            $paid = $member->payments->keyBy('collection_cycle_id');
            $unpaid = fn ($cycle) => (float) ($paid->get($cycle->id)?->amount ?? 0) < (float) $cycle->expected_amount;
            $annual = $cycles->where('type', 'Annual Dues')->filter(function ($cycle) use ($firstYear, $year) {
                $cycleYear = PaymentCycleColumns::year($cycle);

                return $cycleYear !== null && $cycleYear >= $firstYear && $cycleYear <= $year;
            });
            $current = $annual->first(fn ($cycle) => PaymentCycleColumns::year($cycle) === $year);
            $annualPaid = $year < $firstYear || ($current && ! $unpaid($current));
            $annualMissing = $annual->filter($unpaid)->pluck('name');
            $dayong = $cycles->where('type', 'Dayong')->filter(fn ($cycle) => ! $member->start_cycle_id || $cycle->id >= $member->start_cycle_id);
            $missing = $dayong->filter($unpaid)->pluck('name');
            $missed = $dayong->sortByDesc('id')->take(2)->filter($unpaid)->count();
            $recommendation = 'No action';
            $reasons = [];
            $references = ['Section 10'];
            if ($year < self::FIRST_COMPLIANCE_YEAR) {
                $reasons[] = 'Strict annual-dues compliance begins in '.self::FIRST_COMPLIANCE_YEAR.'.';
            } elseif ($year < $firstYear) {
                $reasons[] = "Registration covers {$registered}; separate annual dues begin {$firstYear}.";
            } elseif (! $current) {
                $reasons[] = "No {$year} annual-dues cycle exists; compliance cannot yet be confirmed.";
            } elseif (! $annualPaid) {
                $reasons[] = 'Current annual dues are not fully paid.';
                if (now()->month >= 2) {
                    $reasons[] = 'Insurance coverage is lost after one month under Section 4D.';
                    $references[] = 'Section 4D';
                }
                if (now()->month >= 3) {
                    $reasons[] = 'Review for Inactive status under Section 4E.';
                    $references[] = 'Section 4E';
                }
            }
            if ($annualMissing->isNotEmpty()) {
                $reasons[] = 'Unpaid annual dues: '.$annualMissing->join(', ').'.';
            }
            if ($missing->isNotEmpty()) {
                $reasons[] = 'Unpaid mortuary contributions: '.$missing->join(', ').'.';
            }
            if ($missed >= 2) {
                $recommendation = 'Subject for Board expulsion review';
                $reasons[] = 'The latest two mortuary contributions are unpaid. Board action/resolution must still be recorded.';
            } elseif ($member->member_status === 'Active' && ! $annualPaid && $current && now()->month >= 3) {
                $recommendation = 'Review for Inactive status';
            }
            if ($member->member_status === 'Inactive') {
                $reasons[] = 'Recorded Inactive; reinstatement requires full payment of arrears (Section 14A).';
                $references[] = 'Section 14A';
            }
            if ($member->member_status === 'Expelled') {
                $reasons[] = 'Recorded Expelled; reinstatement requires a Council Grand Knight request, Board approval and full payment of arrears (Section 14B).';
                $references[] = 'Section 14B';
            }
            $standing = $member->member_status === 'Active' && $annualPaid && $missing->isEmpty();
            if ($standing) {
                $reasons[] = 'In good standing under Section 10.';
            }
            if ($member->remarks) {
                $reasons[] = 'Officer remarks: '.$member->remarks;
            }

            $dueCents = fn ($cycle) => max(0, (int) round(((float) $cycle->expected_amount - (float) ($paid->get($cycle->id)?->amount ?? 0)) * 100));

            $annualLabel = $year < self::FIRST_COMPLIANCE_YEAR ? 'Starts '.self::FIRST_COMPLIANCE_YEAR : ($year < $firstYear ? "Registration covers {$registered}" : ($annualMissing->join(', ') ?: ($current ? 'Paid' : 'Cycle missing')));
            $reportReason = $standing ? 'Active with required payments recorded.' : 'Recorded status: '.$member->member_status.'. Annual dues: '.$annualLabel.'. Unpaid mortuary contributions: '.($missing->join(', ') ?: 'None').'.';
            if ($member->remarks) {
                $reportReason .= ' Officer remarks: '.$member->remarks;
            }

            return $row + ['annual' => $annualLabel, 'missed' => $missed, 'unpaid' => $missing->join(', ') ?: 'None', 'standing' => $standing ? 'Yes' : 'No', 'recommendation' => $recommendation, 'reason' => implode(' ', $reasons), 'report_reason' => $reportReason,
                'annual_due_cents' => $annual->sum($dueCents), 'mortuary_due_cents' => $dayong->sum($dueCents),
                'bylaw_references' => array_values(array_unique($references)), 'board_reference_unverified' => $missed >= 2];
        });
    }
}
