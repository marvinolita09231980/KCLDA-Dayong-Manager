<div class="payment-report-preview">
    <style>
        .payment-report-preview { min-width: 0; color: #203750; }
        .payment-report-preview h3 { font-size: 20px; font-weight: 700; margin-bottom: 10px; }
        .payment-report-preview p { margin: 6px 0; font-size: 14px; line-height: 1.5; }
        .payment-report-preview .report-scroll { max-height: 55vh; overflow: auto; margin-top: 16px; border: 1px solid #dbe2ea; }
        .payment-report-preview table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .payment-report-preview th, .payment-report-preview td { padding: 12px 16px; border: 1px solid #dbe2ea; text-align: left; min-width: 130px; }
        .payment-report-preview th { background: #edf2f7; position: sticky; top: 0; }
        .payment-report-preview .amount { text-align: right; white-space: nowrap; }
        .payment-report-preview .totals { background: #edf2f7; font-weight: 700; }
    </style>
    @if($report)
        <h3>{{ $report['title'] }}</h3>
        <p><strong>{{ number_format(count($report['rows'])) }} members</strong> &middot; {{ $report['council'] ?: 'All councils' }}</p>
        <p>Member-list cycles: {{ $report['filterCycles'] }}</p>
        <p>Member-list payment dates: {{ $report['dateFrom'] ?: 'Any start date' }} to {{ $report['dateTo'] ?: 'Any end date' }}.</p>
        <p>Cycle columns show recorded payments. Amount to be collected is the remaining balance for the selected applicable cycles, after payments. Same-year registration and annual dues count as one charge. Overpayments in one cycle do not reduce another cycle's balance.</p>
        <p>Each member appears once, with all selected cycles in separate columns. PDFs use portrait 8.5 &times; 13-inch paper and fit all columns across the page.</p>
        <p>Images contain up to 15 members each, with the report header and column headings repeated. Images widen to fit all selected columns. Multiple PNG images download together as a ZIP. The report grand total appears on the last image.</p>
        <div class="report-scroll" tabindex="0" role="region" aria-label="Report preview, scroll to review all rows and columns">
            @include('reports.payment-report-table', ['indices' => array_keys($report['headers'])])
        </div>
    @else
        <p role="alert">{{ $previewError ?? 'Select at least one cycle column to preview the report.' }}</p>
    @endif
</div>
