@if($financialPosition)
    <section class="ledger-panel" aria-label="Current available funds">
        <div class="ledger-panel-header"><div><h2>Current available funds</h2><p>Current Dayong cash and commitments across all dates, independent of the report filters.</p></div></div>
        <div class="ledger-table-scroll">
            <table class="ledger-table">
                <tbody>
                    <tr><th scope="row">Cash balance</th><td class="ledger-money">PHP {{ number_format($financialPosition['currentDayongBalanceCents'] / 100, 2) }}</td></tr>
                    <tr><th scope="row">Less: Outstanding payables</th><td class="ledger-money">PHP {{ number_format($financialPosition['outstandingPayablesCents'] / 100, 2) }}</td></tr>
                    @forelse($financialPosition['outstandingPayables'] as $payable)
                        <tr><td><strong>{{ $payable->payee }}</strong><br>{{ $payable->particulars }}<br><small>{{ $payable->due_date ? 'Due '.$payable->due_date->format('M j, Y') : 'No due date' }}</small></td><td class="ledger-money">PHP {{ number_format((float) $payable->amount, 2) }}</td></tr>
                    @empty
                        <tr><td colspan="2">No outstanding payables.</td></tr>
                    @endforelse
                    <tr><th scope="row">Available balance</th><td class="ledger-money"><strong>PHP {{ number_format($financialPosition['availableBalanceCents'] / 100, 2) }}</strong></td></tr>
                </tbody>
            </table>
        </div>
    </section>
@endif
