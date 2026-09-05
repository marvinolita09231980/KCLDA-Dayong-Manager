<?php

namespace App\Services;

use App\Models\CollectionCycle;
use App\Models\Member;
use Illuminate\Support\Collection;

class ComplianceReport
{
    public function rows(): Collection
    {
        $cycles = CollectionCycle::orderBy('id')->get();

        return Member::with('payments')->orderBy('council')->orderBy('last_name')->get()->map(function (Member $member) use ($cycles) {
            $row = ['id' => $member->id, 'name' => $member->full_name, 'council' => $member->council, 'status' => $member->member_status];
            $payments = $member->payments->keyBy('collection_cycle_id');
            $row['payment_cycles'] = $cycles->map(function ($cycle) use ($payments) {
                $payment = $payments->get($cycle->id);
                $amount = (float) ($payment?->amount ?? 0);

                return [
                    'name' => $cycle->name,
                    'type' => $cycle->type,
                    'expected' => (float) $cycle->expected_amount,
                    'paid' => $amount,
                    'date' => $payment?->date_paid?->format('M j, Y'),
                    'receipt' => $payment?->receipt_number,
                    'notes' => $payment?->notes,
                    'status' => ! $payment ? 'No payment recorded' : ($amount >= (float) $cycle->expected_amount ? 'Paid' : ($amount > 0 ? 'Partial payment' : 'Unpaid')),
                ];
            });
            $payments = $member->payments->keyBy('collection_cycle_id');
            $row['payment_cycles'] = $cycles->map(function ($cycle) use ($payments) {
                $payment = $payments->get($cycle->id);
                $amount = (float) ($payment?->amount ?? 0);

                return [
                    'name' => $cycle->name,
                    'type' => $cycle->type,
                    'expected' => (float) $cycle->expected_amount,
                    'paid' => $amount,
                    'date' => $payment?->date_paid?->format('M j, Y'),
                    'receipt' => $payment?->receipt_number,
                    'notes' => $payment?->notes,
                    'status' => ! $payment ? 'No payment recorded' : ($amount >= (float) $cycle->expected_amount ? 'Paid' : ($amount > 0 ? 'Partial payment' : 'Unpaid')),
                ];
            });
            if ($member->member_status === 'Deceased') {
                return $row + ['annual' => '—', 'missed' => 0, 'unpaid' => '—', 'standing' => '—', 'recommendation' => '', 'reason' => 'Deceased'];
            }
            $year = now()->year;
            $registered = $member->registration_date?->year;
            $firstYear = $registered >= 2027 ? $registered + 1 : 2027;
            $paid = $member->payments->keyBy('collection_cycle_id');
            $unpaid = fn ($cycle) => (float) ($paid->get($cycle->id)?->amount ?? 0) < (float) $cycle->expected_amount;
            $annual = $cycles->where('type', 'Annual Dues')->filter(function ($cycle) use ($firstYear, $year) {
                return preg_match('/\b(20\d{2})\b/', $cycle->name, $match) && (int) $match[1] >= $firstYear && (int) $match[1] <= $year;
            });
            $current = $annual->first(fn ($cycle) => preg_match('/\b'.$year.'\b/', $cycle->name));
            $annualPaid = $year < $firstYear || ($current && ! $unpaid($current));
            $annualMissing = $annual->filter($unpaid)->pluck('name');
            $dayong = $cycles->where('type', 'Dayong')->filter(fn ($cycle) => ! $member->start_cycle_id || $cycle->id >= $member->start_cycle_id);
            $missing = $dayong->filter($unpaid)->pluck('name');
            $missed = $dayong->sortByDesc('id')->take(2)->filter($unpaid)->count();
            $recommendation = 'No action';
            $reasons = [];
            if ($year < 2027) {
                $reasons[] = 'Strict annual-dues compliance begins in 2027.';
            } elseif ($year < $firstYear) {
                $reasons[] = "Registration covers {$registered}; separate annual dues begin {$firstYear}.";
            } elseif (! $current) {
                $reasons[] = "No {$year} annual-dues cycle exists; compliance cannot yet be confirmed.";
            } elseif (! $annualPaid) {
                $reasons[] = 'Current annual dues are not fully paid.';
                if (now()->month >= 2) {
                    $reasons[] = 'Insurance coverage is lost after one month under Section 4D.';
                }
                if (now()->month >= 3) {
                    $reasons[] = 'Review for Inactive status under Section 4E.';
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
            }
            if ($member->member_status === 'Expelled') {
                $reasons[] = 'Recorded Expelled; reinstatement requires a Council Grand Knight request, Board approval and full payment of arrears (Section 14B).';
            }
            $standing = $member->member_status === 'Active' && $annualPaid && $missing->isEmpty();
            if ($standing) {
                $reasons[] = 'In good standing under Section 10.';
            }
            if ($member->remarks) {
                $reasons[] = 'Officer remarks: '.$member->remarks;
            }

            return $row + ['annual' => $year < 2027 ? 'Starts 2027' : ($year < $firstYear ? "Registration covers {$registered}" : ($annualMissing->join(', ') ?: ($current ? 'Paid' : 'Cycle missing'))), 'missed' => $missed, 'unpaid' => $missing->join(', ') ?: 'None', 'standing' => $standing ? 'Yes' : 'No', 'recommendation' => $recommendation, 'reason' => implode(' ', $reasons)];
        });
    }
}
