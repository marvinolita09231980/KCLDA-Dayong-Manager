@php
    $member = $this->getPaymentHistoryMember();
    $payments = $member?->payments()->with('collectionCycle')->orderByDesc('date_paid')->orderByDesc('id')->paginate(10, ['*'], 'paymentHistoryPage');
@endphp

<div class="payment-history" aria-live="polite">
    @if (! $member)
        <div class="payment-history-empty">
            <strong>Select a member</strong>
            <p>Their previous payments will appear here so you can review them before recording a new payment.</p>
        </div>
    @else
        <div class="payment-history-summary">
            <div><strong>{{ $member->full_name }}</strong><p>{{ $member->council }}</p></div>
            <span>{{ $payments->total() }} {{ \Illuminate\Support\Str::plural('payment', $payments->total()) }}</span>
        </div>
        @if ($payments->total())
            <div class="payment-history-scroll">
                <table class="payment-history-table">
                    <caption class="sr-only">Payment history for {{ $member->full_name }}</caption>
                    <thead><tr><th scope="col">Date paid</th><th scope="col">Collection cycle</th><th scope="col">Receipt</th><th scope="col" class="payment-history-amount">Amount</th></tr></thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            <tr wire:key="payment-history-{{ $payment->id }}">
                                <td data-label="Date paid">{{ $payment->date_paid?->format('M j, Y') ?? '—' }}</td>
                                <td data-label="Collection cycle">{{ $payment->collectionCycle?->name ?? '—' }}</td>
                                <td data-label="Receipt">{{ $payment->receipt_number ?: '—' }}</td>
                                <td data-label="Amount" class="payment-history-amount">₱{{ number_format((float) $payment->amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="payment-history-pagination">{{ $payments->links() }}</div>
        @else
            <div class="payment-history-empty"><strong>No payments yet</strong><p>This member’s recorded payments will appear here.</p></div>
        @endif
    @endif
</div>
