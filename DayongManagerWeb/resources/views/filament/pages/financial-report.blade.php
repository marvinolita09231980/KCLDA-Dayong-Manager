<x-filament-panels::page>
    <link rel="stylesheet" href="{{ asset('css/financial-ledger.css') }}?v={{ filemtime(public_path('css/financial-ledger.css')) }}">
    <div class="ledger-page">
        @include('filament.pages.available-balance')
        <section class="ledger-hero" aria-label="Financial report overview">
            <div>
                <span class="ledger-eyebrow">FINANCE / REPORTS</span>
                <h2>Financial Report</h2>
                <p>{{ $dateFrom ?: 'Beginning of records' }} &mdash; {{ $dateTo ?: 'Latest records' }}</p>
            </div>
            <span class="ledger-hero-icon"><x-heroicon-o-document-chart-bar aria-hidden="true" /></span>
        </section>

        <section class="ledger-panel" aria-label="Report dates">
            <div class="ledger-filters">
                <label for="report-from"><span>Date from</span><input id="report-from" type="date" wire:model.live="dateFrom" /></label>
                <label for="report-to"><span>Date to</span><input id="report-to" type="date" wire:model.live="dateTo" /></label>
                <button type="button" class="ledger-clear" wire:click="clearDates">All dates</button>
            </div>
            @foreach($dateErrors as $error)
                <p class="ledger-error" role="alert">{{ $error }}</p>
            @endforeach
        </section>

        @if($dateErrors === [])
            <section class="ledger-summary" aria-label="Financial totals">
                @if($canCollections)
                    <article class="ledger-stat ledger-stat-payment"><div>Total collections</div><strong>PHP {{ number_format($collectionCents / 100, 2) }}</strong><p>{{ number_format($collections->sum('record_count')) }} payment records</p></article>
                @endif
                @if($canDisbursements)
                    <article class="ledger-stat ledger-stat-disbursement"><div>Total disbursements</div><strong>PHP {{ number_format($disbursementCents / 100, 2) }}</strong><p>{{ number_format($disbursements->sum('record_count')) }} disbursement records</p></article>
                @endif
                @if($canCollections && $canDisbursements)
                    <article class="ledger-stat ledger-stat-bank"><div>Net collections less disbursements</div><strong>PHP {{ number_format($netCents / 100, 2) }}</strong><p>Difference for the selected period; this is not a bank balance.</p></article>
                @endif
            </section>

            @foreach([
                ['visible' => $canCollections, 'title' => 'Collections by collection cycle', 'column' => 'Collection cycle', 'rows' => $collections, 'total' => $collectionCents],
                ['visible' => $canDisbursements, 'title' => 'Disbursements by category', 'column' => 'Category', 'rows' => $disbursements, 'total' => $disbursementCents],
            ] as $section)
                @if($section['visible'])
                    <section class="ledger-panel" aria-label="{{ $section['title'] }}">
                        <div class="ledger-panel-header"><h2>{{ $section['title'] }}</h2></div>
                        <div class="ledger-table-scroll" tabindex="0" aria-label="{{ $section['title'] }} table">
                            <table class="ledger-table">
                                <thead><tr><th scope="col">{{ $section['column'] }}</th><th scope="col">Records</th><th scope="col" class="ledger-money">Amount</th></tr></thead>
                                <tbody>
                                    @forelse($section['rows'] as $row)
                                        <tr><th scope="row">{{ $row->label }}</th><td>{{ number_format($row->record_count) }}</td><td class="ledger-money">PHP {{ number_format($row->cents / 100, 2) }}</td></tr>
                                    @empty
                                        <tr><td colspan="3">No records in the selected period.</td></tr>
                                    @endforelse
                                </tbody>
                                <tfoot><tr><th scope="row">Total</th><td>{{ number_format($section['rows']->sum('record_count')) }}</td><td class="ledger-money"><strong>PHP {{ number_format($section['total'] / 100, 2) }}</strong></td></tr></tfoot>
                            </table>
                        </div>
                    </section>
                @endif
            @endforeach

            <div class="ledger-footer">
                <div class="ledger-footer-copy">
                    <span>Bank deposits and withdrawals are excluded from these totals to avoid counting transfers as collections or expenses.</span>
                    @if($canCollections && $undatedPayments > 0)
                        <span>{{ number_format($undatedPayments) }} payment records have no payment date and are {{ $dateFrom !== '' || $dateTo !== '' ? 'excluded from this date-filtered report' : 'included in this all-dates report' }}.</span>
                    @endif
                    @if(!$canCollections || !$canDisbursements)
                        <span>This report shows only the records your account can view.</span>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
