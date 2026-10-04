<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Collection Statistics and Financial Report</title>
    <style>
        @page { margin: 26px 28px 38px; }
        body { font-family: Arial, Helvetica, sans-serif; color: #203750; font-size: 12pt; }
        h1 { margin: 0 0 7px; font-size: 18pt; }
        h2 { margin: 20px 0 8px; padding-bottom: 5px; border-bottom: 2px solid #244569; font-size: 14pt; }
        p { margin: 3px 0; line-height: 1.45; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 8px; font-size: 12pt; }
        th, td { border: 1px solid #ccd5df; padding: 5px; text-align: left; vertical-align: top; }
        thead th { background: #edf2f7; }
        tr { page-break-inside: avoid; }
        .money, .number { text-align: right; white-space: nowrap; }
        .total { background: #edf2f7; font-weight: bold; }
        .balance { background: #e8f5ed; font-weight: bold; }
        .subrow th { padding-left: 18px; font-weight: normal; }
    </style>
</head>
<body>
    <h1>KCLDA — Collection Statistics and Financial Report</h1>
    <p>Generated {{ $report['generatedAt'] }}</p>
    <p>Collection cycles: {{ $report['filterCycles'] ?: 'All cycles' }} · Payment dates: {{ $report['paymentDates'] }}</p>
    @if($report['yearRange'])<p>Selected years: {{ $report['yearRange'] }}</p>@endif

    <h2>Collection cycle summary</h2>
    <table>
        <thead><tr><th>Cycle</th><th class="number">Total members</th><th class="number">Paid members</th><th class="number">Unpaid members</th><th class="money">Amount paid</th></tr></thead>
        <tbody>
            @foreach($report['cycleSummaries'] as $summary)
                <tr><th>{{ $summary['name'] }}</th><td class="number">{{ number_format($summary['totals']['paid'] + $summary['totals']['unpaid']) }}</td><td class="number">{{ number_format($summary['totals']['paid']) }}</td><td class="number">{{ number_format($summary['totals']['unpaid']) }}</td><td class="money">PHP {{ number_format($summary['totals']['amount_cents'] / 100, 2) }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>Collections per council and cycle</h2>
    <table>
        <thead><tr><th>Council / Cycle</th><th class="number">Members</th><th class="number">Paid</th><th class="number">Unpaid</th><th class="money">Amount paid</th></tr></thead>
        <tbody>
            @foreach($report['rows'] as $council)
                @foreach($council['cycles'] as $cycle)
                    <tr class="{{ $loop->first ? '' : 'subrow' }}"><th>{{ $loop->first ? 'Council '.$council['council'].' — ' : '' }}{{ $cycle['name'] }}</th><td class="number">{{ number_format($cycle['paid'] + $cycle['unpaid']) }}</td><td class="number">{{ number_format($cycle['paid']) }}</td><td class="number">{{ number_format($cycle['unpaid']) }}</td><td class="money">PHP {{ number_format($cycle['amount_cents'] / 100, 2) }}</td></tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    @if($report['yearFinancial'])
        @php($annual = $report['yearFinancial'])
        <h2>Financial statement — {{ $report['yearRange'] }}</h2>
        <p>All payments and disbursements during the selected years are included, regardless of collection cycle. Payments without a payment date use their recorded date.</p>
        <table>
            <thead><tr><th>Financial item</th><th>Details</th><th class="money">Amount</th></tr></thead>
            <tbody>
                <tr><th>Beginning balance</th><td>Carried from {{ $annual['openingDate']->format('M j, Y') }}</td><td class="money">PHP {{ number_format($annual['openingBalanceCents'] / 100, 2) }}</td></tr>
                <tr><th>Payments received</th><td>All cycles paid in selected years</td><td class="money">PHP {{ number_format($annual['collectionCents'] / 100, 2) }}</td></tr>
                <tr><th>Less: Disbursements</th><td>{{ $annual['disbursementItems']->count() }} records</td><td class="money">PHP {{ number_format($annual['disbursementCents'] / 100, 2) }}</td></tr>
                <tr class="balance"><th>Ending actual balance</th><td>Beginning + payments − disbursements</td><td class="money">PHP {{ number_format($annual['endingBalanceCents'] / 100, 2) }}</td></tr>
                <tr><th>Less: Outstanding payables</th><td>Still unpaid, recorded by {{ $annual['throughDate']->format('M j, Y') }}</td><td class="money">PHP {{ number_format($annual['outstandingPayablesCents'] / 100, 2) }}</td></tr>
                @forelse($annual['outstandingPayables'] as $payable)
                    <tr class="subrow"><th>{{ $payable->payee }}</th><td>{{ $payable->particulars }}<br>{{ $payable->due_date ? 'Due '.$payable->due_date->format('M j, Y') : 'No due date' }}</td><td class="money">PHP {{ number_format((float) $payable->amount, 2) }}</td></tr>
                @empty
                    <tr><td colspan="3">No outstanding payables.</td></tr>
                @endforelse
                <tr class="balance"><th>Available balance</th><td>Ending actual balance less outstanding payables</td><td class="money">PHP {{ number_format($annual['availableBalanceCents'] / 100, 2) }}</td></tr>
            </tbody>
        </table>
        <h2>Payments by collection cycle</h2>
        <table>
            <thead><tr><th>Cycle</th><th class="number">Payment records</th><th class="money">Amount received</th></tr></thead>
            <tbody>
                @forelse($annual['paymentItems'] as $item)
                    <tr><th>{{ $item['cycle'] }}</th><td class="number">{{ number_format($item['records']) }}</td><td class="money">PHP {{ number_format($item['cents'] / 100, 2) }}</td></tr>
                @empty
                    <tr><td colspan="3">No payments in the selected years.</td></tr>
                @endforelse
            </tbody>
        </table>
        <h2>Disbursements in selected years</h2>
        <table>
            <thead><tr><th>Date</th><th>Payee</th><th>Category</th><th>Particulars</th><th class="money">Amount</th></tr></thead>
            <tbody>
                @forelse($annual['disbursementItems'] as $item)
                    <tr><td>{{ $item->disbursement_date->format('M j, Y') }}</td><td>{{ $item->payee }}</td><td>{{ $item->category }}</td><td>{{ $item->particulars }}</td><td class="money">PHP {{ number_format((float) $item->amount, 2) }}</td></tr>
                @empty
                    <tr><td colspan="5">No disbursements in the selected years.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif

    @if($report['financialPosition'])
        @php($financial = $report['financialPosition'])
        <h2>Financial position — {{ $financial['periodStart'] ? $financial['periodStart']->format('M j, Y').' to present' : 'Beginning of records to present' }}</h2>
        <table>
            <thead><tr><th>Financial item</th><th>Details</th><th class="money">Amount</th></tr></thead>
            <tbody>
                <tr><th>Beginning balance</th><td>{{ $financial['closure'] ? 'Closing balance carried forward from '.$financial['closure']->disbursement_date->format('M j, Y') : 'Initial bank deposit' }}</td><td class="money">PHP {{ number_format($financial['beginningBalanceCents'] / 100, 2) }}</td></tr>
                <tr><th>Member collections</th><td>Collections recorded in this reporting period</td><td class="money">PHP {{ number_format($financial['collectionCents'] / 100, 2) }}</td></tr>
                <tr class="total"><th>Total funds</th><td>Beginning balance + collections</td><td class="money">PHP {{ number_format($financial['totalFundsCents'] / 100, 2) }}</td></tr>
                @foreach($financial['disbursementItems'] as $item)
                    <tr class="subrow"><th>Less: {{ $item['category'] }}</th><td>{{ number_format($item['recordCount']) }} {{ $item['recordCount'] === 1 ? 'record' : 'records' }}</td><td class="money">PHP {{ number_format($item['cents'] / 100, 2) }}</td></tr>
                @endforeach
                <tr class="total"><th>Total disbursements</th><td>{{ number_format($financial['ledger']->count()) }} records</td><td class="money">PHP {{ number_format($financial['disbursementCents'] / 100, 2) }}</td></tr>
                <tr class="balance"><th>Current Dayong Actual balance</th><td>Total funds − disbursements</td><td class="money">PHP {{ number_format($financial['currentDayongBalanceCents'] / 100, 2) }}</td></tr>
                <tr><th>Less: Outstanding payables</th><td>Unpaid commitments across all periods</td><td class="money">PHP {{ number_format($financial['outstandingPayablesCents'] / 100, 2) }}</td></tr>
                @forelse($financial['outstandingPayables'] as $payable)
                    <tr class="subrow"><th>{{ $payable->payee }}</th><td>{{ $payable->particulars }}<br><small>{{ $payable->due_date ? 'Due '.$payable->due_date->format('M j, Y') : 'No due date' }}</small></td><td class="money">PHP {{ number_format((float) $payable->amount, 2) }}</td></tr>
                @empty
                    <tr><td colspan="3">No outstanding payables.</td></tr>
                @endforelse
                <tr class="balance"><th>Available balance</th><td>Current Dayong Actual balance less outstanding payables</td><td class="money">PHP {{ number_format($financial['availableBalanceCents'] / 100, 2) }}</td></tr>
            </tbody>
        </table>

        <h2>Disbursement ledger</h2>
        <table>
            <thead><tr><th>Date</th><th>Voucher</th><th>Payee</th><th>Category</th><th>Particulars</th><th class="money">Amount</th></tr></thead>
            <tbody>
                @forelse($financial['ledger'] as $item)
                    <tr><td>{{ $item->disbursement_date->format('M j, Y') }}</td><td>{{ $item->voucher_number ?: '—' }}</td><td>{{ $item->payee ?: '—' }}</td><td>{{ $item->category }}</td><td>{{ $item->particulars ?: '—' }}</td><td class="money">PHP {{ number_format((float) $item->amount, 2) }}</td></tr>
                @empty
                    <tr><td colspan="6">No disbursements in the current reporting period.</td></tr>
                @endforelse
            </tbody>
            <tfoot><tr class="total"><th colspan="5">Total disbursements</th><td class="money">PHP {{ number_format($financial['disbursementCents'] / 100, 2) }}</td></tr></tfoot>
        </table>
    @endif
</body>
</html>
