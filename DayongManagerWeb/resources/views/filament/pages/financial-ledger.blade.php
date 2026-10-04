<x-filament-panels::page>
    <link rel="stylesheet" href="{{ asset('css/financial-ledger.css') }}?v={{ filemtime(public_path('css/financial-ledger.css')) }}">
    @php
        $filtered = $dateFrom !== '' || $dateTo !== '';
        $payments = $summary->get('Payment');
        $disbursements = $summary->get('Disbursement');
        $bank = $summary->get('Bank transaction');
    @endphp

    <div class="ledger-page">
        @include('filament.pages.available-balance')
        <section class="ledger-hero" aria-label="Financial ledger overview">
            <div><span class="ledger-eyebrow">FINANCE / RECORDS</span><h2>Every movement, in one clear view.</h2><p>Review member payments, disbursements, and bank transactions in date order.</p></div>
            <span class="ledger-hero-icon"><x-heroicon-o-clipboard-document-list aria-hidden="true" /></span>
        </section>

        <section class="ledger-summary" aria-label="Summary for selected dates">
            @if(auth()->user()->hasPermission('collections.view'))<article class="ledger-stat ledger-stat-payment"><div><span>Member payments</span><x-heroicon-o-arrow-down-left aria-hidden="true" /></div><strong>{{ number_format((int) ($payments->record_count ?? 0)) }}</strong><p>{{ $payments ? 'PHP '.number_format((float) $payments->total_amount, 2).' collected' : 'No payments in this period' }}</p></article>@endif
            @if(auth()->user()->hasPermission('disbursements.view'))<article class="ledger-stat ledger-stat-disbursement"><div><span>Disbursements</span><x-heroicon-o-arrow-up-right aria-hidden="true" /></div><strong>{{ number_format((int) ($disbursements->record_count ?? 0)) }}</strong><p>{{ $disbursements ? 'PHP '.number_format((float) $disbursements->total_amount, 2).' disbursed' : 'No disbursements in this period' }}</p></article>@endif
            @if(auth()->user()->hasPermission('ledger.view'))<article class="ledger-stat ledger-stat-bank"><div><span>Bank transactions</span><x-heroicon-o-building-library aria-hidden="true" /></div><strong>{{ number_format((int) ($bank->record_count ?? 0)) }}</strong><p>Deposits and withdrawals recorded</p></article>@endif
        </section>

        <section class="ledger-panel" aria-labelledby="ledger-records-heading">
            <div class="ledger-panel-header"><div><span class="ledger-section-kicker">TRANSACTION HISTORY</span><h2 id="ledger-records-heading">Ledger entries</h2><p>{{ number_format($entries->total()) }} {{ $entries->total() === 1 ? 'record' : 'records' }} {{ $filtered ? 'in the selected period' : 'across all dates' }} &middot; Newest first</p></div><span class="ledger-count">{{ number_format($entries->total()) }} total</span></div>
            <div class="ledger-filters" aria-label="Filter ledger by date"><div class="ledger-filter-heading"><x-heroicon-o-funnel aria-hidden="true" /><span>Filter by date</span></div><label for="ledger-date-from"><span>Date from</span><input id="ledger-date-from" type="date" wire:model.live="dateFrom" /></label><label for="ledger-date-to"><span>Date to</span><input id="ledger-date-to" type="date" wire:model.live="dateTo" /></label><button type="button" class="ledger-clear" wire:click="clearDates" @disabled(!$filtered)>Clear dates</button></div>
            @if($invalidRange)<p class="ledger-error" role="alert">Date from must be on or before date to.</p>@endif

            <div class="ledger-table-scroll" tabindex="0" aria-label="Ledger entries table, scroll horizontally for more columns">
                <table class="ledger-table">
                    <thead><tr><th scope="col">Date</th><th scope="col">Record type</th><th scope="col">Member / payee / transaction</th><th scope="col">Details</th><th scope="col">Receipt number</th><th scope="col" class="ledger-money">Amount</th></tr></thead>
                    <tbody>
                        @forelse($entries as $entry)
                            @php
                                $typeClass = match ($entry->entry_type) { 'Payment' => 'payment', 'Disbursement' => 'disbursement', default => 'bank' };
                                $party = $entry->entry_type === 'Payment' ? trim($entry->party.' '.$entry->secondary) : $entry->party;
                            @endphp
                            <tr wire:key="ledger-{{ $entry->entry_type }}-{{ $entry->record_id }}">
                                <td class="ledger-date">{{ $entry->entry_date ? \Illuminate\Support\Carbon::parse($entry->entry_date)->format('M j, Y') : 'No date entered' }}</td>
                                <td><span class="ledger-type ledger-type-{{ $typeClass }}"><span class="ledger-type-dot"></span>{{ $entry->entry_type }}</span></td>
                                <td><strong class="ledger-party">{{ $party }}</strong>@if($entry->entry_type === 'Disbursement' && $entry->secondary)<small>{{ $entry->secondary }}</small>@endif</td>
                                <td class="ledger-details">{{ $entry->details ?: '—' }}</td>
                                <td><span class="ledger-reference">{{ $entry->reference ?: '—' }}</span></td>
                                <td class="ledger-money"><strong>PHP {{ number_format((float) $entry->amount, 2) }}</strong></td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><div class="ledger-empty"><x-heroicon-o-inbox aria-hidden="true" /><h3>No records found</h3><p>{{ $filtered ? 'Try another date range to see more entries.' : 'Payments, disbursements, and bank transactions will appear here once recorded.' }}</p>@if($filtered)<button type="button" wire:click="clearDates">Clear date filters</button>@endif</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="ledger-footer">
                <div class="ledger-footer-copy">
                    <strong>Showing {{ $entries->total() ? number_format($entries->firstItem()) : 0 }}–{{ $entries->total() ? number_format($entries->lastItem()) : 0 }} of {{ number_format($entries->total()) }}</strong>
                    <span>Payments without a payment date appear last and are excluded when dates are filtered.</span>
                </div>
                @if($entries->lastPage() > 1)
                    @php
                        $currentPage = $entries->currentPage();
                        $lastPage = $entries->lastPage();
                        $pageNumbers = collect([1, $lastPage, ...range(max(1, $currentPage - 2), min($lastPage, $currentPage + 2))])->unique()->sort()->values();
                        $previousPageNumber = null;
                    @endphp
                    <nav class="ledger-pagination" aria-label="Ledger pages">
                        <button type="button" class="ledger-page-control" wire:click="previousPage" @disabled($entries->onFirstPage()) aria-label="Previous page"><x-heroicon-o-chevron-left aria-hidden="true" /><span>Previous</span></button>
                        <div class="ledger-page-numbers">
                            @foreach($pageNumbers as $pageNumber)
                                @if($previousPageNumber !== null && $pageNumber > $previousPageNumber + 1)<span class="ledger-page-ellipsis" aria-hidden="true">&hellip;</span>@endif
                                <button type="button" wire:click="gotoPage({{ $pageNumber }})" class="ledger-page-number {{ $pageNumber === $currentPage ? 'is-current' : '' }}" aria-label="Page {{ $pageNumber }}" @if($pageNumber === $currentPage) aria-current="page" @endif>{{ $pageNumber }}</button>
                                @php($previousPageNumber = $pageNumber)
                            @endforeach
                        </div>
                        <button type="button" class="ledger-page-control" wire:click="nextPage" @disabled(!$entries->hasMorePages()) aria-label="Next page"><span>Next</span><x-heroicon-o-chevron-right aria-hidden="true" /></button>
                    </nav>
                @endif
            </div>
        </section>
    </div>
</x-filament-panels::page>
