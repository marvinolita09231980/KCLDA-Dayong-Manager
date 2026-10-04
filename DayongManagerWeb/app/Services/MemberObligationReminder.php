<?php

namespace App\Services;

use App\Models\Member;

class MemberObligationReminder
{
    public function prepare(Member $member, array $filters): array
    {
        $columns = PaymentCycleColumns::selected($filters);
        // The table may contain only date-filtered payments. Reload the full history
        // before asking a member to settle an actual outstanding balance.
        $member = $member->fresh(['payments']);
        $dues = $columns->map(fn ($column) => [
            'cycle' => PaymentCycleColumns::charge($member, $column)?->name ?? $column['name'],
            'cents' => PaymentCycleColumns::outstandingCents($member, $column),
        ])->filter(fn ($due) => $due['cents'] > 0)->values();
        $total = $dues->sum('cents');
        $message = '';
        if ($total > 0) {
            $lines = $dues->map(fn ($due) => '- '.$due['cycle'].': PHP '.number_format($due['cents'] / 100, 2))->all();
            $message = "Hello {$member->full_name},\n\nThis is a friendly reminder from KCLDA regarding your outstanding obligations for the following collections:\n\n"
                .implode("\n", $lines)
                ."\n\nAmount to be collected: PHP ".number_format($total / 100, 2)
                ."\n\nPlease coordinate with your council collector to settle these dues. If you have already paid, please share your receipt so we can update our records. Thank you.";
        }

        return ['memberName' => $member->full_name, 'message' => $message, 'totalCents' => $total];
    }
}
