@if(auth()->user()->hasPermission('collections.view'))
    <section class="compliance-payments" aria-label="Payment details for {{ $row['name'] }}">
        <h4>Payment details · All cycles</h4>
        <p>Recorded payments across registration, annual dues and mortuary cycles. A cycle without a payment record does not necessarily mean the member owes that amount.</p>
        <div class="compliance-payment-scroll" tabindex="0" role="region" aria-label="Payment cycle table">
            <table>
                <thead><tr><th scope="col">Cycle</th><th scope="col">Cycle amount</th><th scope="col">Amount paid</th><th scope="col">Payment status</th><th scope="col">Date paid</th><th scope="col">Receipt / Notes</th></tr></thead>
                <tbody>
                    @forelse($row['payment_cycles'] as $cycle)
                        <tr>
                            <th scope="row">{{ $cycle['name'] }}<small>{{ $cycle['type'] }}</small></th>
                            <td data-label="Cycle amount" class="compliance-payment-money">₱{{ number_format($cycle['expected'], 2) }}</td>
                            <td data-label="Amount paid" class="compliance-payment-money">₱{{ number_format($cycle['paid'], 2) }}</td>
                            <td data-label="Payment status"><span class="compliance-badge compliance-tone-{{ $cycle['status'] === 'Paid' ? 'green' : (in_array($cycle['status'], ['Partial payment', 'Unpaid']) ? 'amber' : 'navy') }}">{{ $cycle['status'] }}</span></td>
                            <td data-label="Date paid">{{ $cycle['date'] ?: '—' }}</td>
                            <td data-label="Receipt / Notes">{{ $cycle['receipt'] ?: '—' }}@if($cycle['notes'])<small>{{ $cycle['notes'] }}</small>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No collection cycles have been created.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="compliance-payment-total">Total recorded payments: <strong>₱{{ number_format($row['payment_cycles']->sum('paid'), 2) }}</strong></p>
    </section>
@endif
