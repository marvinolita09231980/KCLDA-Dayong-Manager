<div class="payment-history">
    <div class="payment-history-summary">
        <div><strong>{{ $member->full_name }}</strong><p>{{ $payments->count() }} {{ \Illuminate\Support\Str::plural('payment', $payments->count()) }} recorded</p></div>
        <div class="member-detail-total"><small>Total paid</small><strong>₱{{ number_format((float) $payments->sum('amount'), 2) }}</strong></div>
    </div>
    @if ($payments->isEmpty())
        <div class="payment-history-empty">No payments recorded for this member.</div>
    @else
        <div class="payment-history-scroll member-detail-payments" tabindex="0" role="region" aria-label="Member payment history">
            <table class="payment-history-table">
                <caption class="sr-only">Payments for {{ $member->full_name }}</caption>
                <thead><tr><th scope="col">Date paid</th><th scope="col">Collection cycle</th><th scope="col">Receipt / Notes</th><th scope="col" class="payment-history-amount">Amount</th></tr></thead>
                <tbody>
                    @foreach ($payments as $payment)
                        <tr>
                            <td data-label="Date paid">{{ $payment->date_paid?->format('M j, Y') ?? '—' }}</td>
                            <td data-label="Collection cycle">{{ $payment->collectionCycle?->name ?? '—' }}</td>
                            <td data-label="Receipt / Notes" class="member-detail-payment-notes">{{ $payment->receipt_number ?: '—' }}@if (filled($payment->notes))<p>{{ $payment->notes }}</p>@endif</td>
                            <td data-label="Amount" class="payment-history-amount">₱{{ number_format((float) $payment->amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
