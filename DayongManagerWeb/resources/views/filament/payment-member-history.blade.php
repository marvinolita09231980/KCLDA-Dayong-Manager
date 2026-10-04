<div class="member-payments-modal">
    <div class="member-payments-overview">
        <div>
            <span class="member-payments-eyebrow">Payment history</span>
            <h3>{{ $member->full_name }}</h3>
            <p>Council {{ $member->council }} <span aria-hidden="true">&middot;</span> {{ $payments->count() }} {{ \Illuminate\Support\Str::plural('payment', $payments->count()) }} recorded</p>
        </div>
        <div class="member-payments-total"><span>Total paid</span><strong>&#8369;{{ number_format((float) $payments->sum('amount'), 2) }}</strong></div>
    </div>
    <p class="member-payments-hint">All recorded payments, newest first.</p>
    <div class="member-payments-scroll" tabindex="0" role="region" aria-label="All payments for {{ $member->full_name }}">
        <table class="member-payments-table">
            <caption class="sr-only">Complete payment history for {{ $member->full_name }}</caption>
            <thead><tr><th scope="col">Date paid</th><th scope="col">Collection cycle</th><th scope="col">Receipt</th><th scope="col">Notes</th><th scope="col" class="member-payments-amount">Amount</th></tr></thead>
            <tbody>
                @forelse ($payments as $payment)
                    <tr>
                        <td class="member-payments-date">{{ $payment->date_paid?->format('M j, Y') ?? 'Not recorded' }}</td>
                        <td><strong>{{ $payment->collectionCycle?->name ?? 'Not recorded' }}</strong>@if ($payment->collectionCycle?->type)<small>{{ $payment->collectionCycle->type }}</small>@endif</td>
                        <td><span class="member-payments-receipt">{{ $payment->receipt_number ?: '—' }}</span></td>
                        <td class="member-payments-notes">{{ $payment->notes ?: '—' }}</td>
                        <td class="member-payments-amount">&#8369;{{ number_format((float) $payment->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">No payments recorded for this member.</td></tr>
                @endforelse
            </tbody>
            <tfoot><tr><th scope="row" colspan="4">Total paid</th><td class="member-payments-amount">&#8369;{{ number_format((float) $payments->sum('amount'), 2) }}</td></tr></tfoot>
        </table>
    </div>
</div>
