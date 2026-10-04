<x-filament-panels::page>
    <link rel="stylesheet" href="{{ asset('css/collection-statistics.css') }}?v={{ filemtime(public_path('css/collection-statistics.css')) }}">
    <div class="collection-statistics">
        <section class="statistics-range" aria-labelledby="statistics-range-heading">
            <h2 id="statistics-range-heading">Report years</h2>
            <p>Choose the first and last year. The page and downloaded report include collection cycles from those years and the financial activity during that period. Leave either end blank to include the earliest or latest year.</p>
            <div class="statistics-range-controls">
                <label for="statistics-year-start"><span>Start year</span><select id="statistics-year-start" wire:model.live="startYear"><option value="">Earliest year</option>@foreach($yearOptions as $year)<option value="{{ $year }}">{{ $year }}</option>@endforeach</select></label>
                <label for="statistics-year-end"><span>End year</span><select id="statistics-year-end" wire:model.live="endYear"><option value="">Latest year</option>@foreach($yearOptions as $year)<option value="{{ $year }}">{{ $year }}</option>@endforeach</select></label>
                <button type="button" wire:click="clearYearRange" @disabled(! $yearRangeActive)>Clear range</button>
            </div>
            @if($yearRangeActive)<p class="statistics-range-selection">Showing {{ $yearRangeLabel }}.</p>@endif
        </section>
        <p>Table filters can narrow the collection statistics further. The year financial statement always includes all payments and disbursements recorded during the selected years.</p>
        <div wire:loading.class="statistics-loading" wire:target="tableFilters,startYear,endYear">
            @if($zeroPayments->isNotEmpty())
                <x-filament::section heading="Payment records with no amount">
                    <p>These saved records have ₱0.00, so they count as unpaid and add nothing to the amount collected. Check each receipt and correct the amount if payment was received.</p>
                    <div class="statistics-table-scroll" tabindex="0" role="region" aria-label="Zero amount payment records">
                        <table>
                            <thead><tr><th scope="col">Member</th><th scope="col">Council</th><th scope="col">Cycle</th><th scope="col">Receipt</th><th scope="col">Record</th></tr></thead>
                            <tbody>
                                @foreach($zeroPayments as $payment)
                                    <tr><th scope="row">{{ $payment['member'] }}</th><td>{{ $payment['council'] }}</td><td>{{ $payment['cycle'] }}</td><td>{{ $payment['receipt'] }}</td><td>
                                        @if(\App\Filament\Resources\Payments\PaymentResource::canEdit(null))
                                            <a href="{{ \App\Filament\Resources\Payments\PaymentResource::getUrl('edit', ['record' => $payment['id']]) }}">Review payment</a>
                                        @else
                                            View only
                                        @endif
                                    </td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            @endif
            {{ $this->table }}
            <section class="statistics-cycle-summary" aria-labelledby="cycle-summary-heading">
                <h3 id="cycle-summary-heading" class="statistics-cycle-title">Collection cycle summary</h3>
                <div class="statistics-table-scroll" tabindex="0" role="region" aria-label="Collection cycle summary">
                    <table>
                        <thead><tr><th scope="col">Cycle</th><th scope="col">Total members</th><th scope="col">Total paid members</th><th scope="col">Total unpaid members</th><th scope="col">Total amount paid</th></tr></thead>
                        <tbody>
                            @forelse($cycleSummaries as $summary)
                                <tr wire:key="cycle-summary-{{ $summary['id'] }}">
                                    <th scope="row">{{ $summary['name'] }}</th>
                                    <td>{{ number_format($summary['totals']['paid'] + $summary['totals']['unpaid']) }}</td>
                                    <td>{{ number_format($summary['totals']['paid']) }}</td>
                                    <td>{{ number_format($summary['totals']['unpaid']) }}</td>
                                    <td>₱{{ number_format($summary['totals']['amount_cents'] / 100, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5">No collection cycles available.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="statistics-table-scroll" tabindex="0" role="region" aria-label="Overall selected cycle counts">
                    <table>
                        <thead><tr><th scope="col">Members in selected cycles</th><th scope="col">Payment records</th><th scope="col">Zero amount records</th></tr></thead>
                        <tbody><tr><td>{{ number_format($totals['paid'] + $totals['unpaid']) }}</td><td>{{ number_format($totals['payment_records']) }}</td><td>{{ number_format($totals['zero_payment_records']) }}</td></tr></tbody>
                    </table>
                </div>
            </section>
            @if($yearFinancial)
                <section class="statistics-cycle-summary statistics-financial-report" aria-labelledby="cycle-balance-heading">
                    <h3 id="cycle-balance-heading" class="statistics-cycle-title">Financial statement · {{ $yearRangeLabel }}</h3>
                    <p>All payments and disbursements dated {{ $yearFinancial['startYear'] }} through {{ $yearFinancial['throughDate']->format('M j, Y') }} are included, even when a payment belongs to a cycle outside the selected years. Payments without a payment date use their recorded date.</p>
                    <div class="statistics-table-scroll" tabindex="0" role="region" aria-label="Selected year financial statement">
                        <table>
                            <thead><tr><th scope="col">Financial item</th><th scope="col">Details</th><th scope="col">Amount</th></tr></thead>
                            <tbody>
                                <tr><th scope="row">Beginning balance</th><td>Balance carried from {{ $yearFinancial['openingDate']->format('M j, Y') }}</td><td>₱{{ number_format($yearFinancial['openingBalanceCents'] / 100, 2) }}</td></tr>
                                <tr><th scope="row">Payments received</th><td>All cycles paid during selected years</td><td>₱{{ number_format($yearFinancial['collectionCents'] / 100, 2) }}</td></tr>
                                <tr><th scope="row">Less: Disbursements</th><td>{{ $yearFinancial['disbursementItems']->count() }} recorded</td><td>₱{{ number_format($yearFinancial['disbursementCents'] / 100, 2) }}</td></tr>
                                <tr class="statistics-current-balance"><th scope="row">Ending actual balance</th><td>Beginning + payments − disbursements</td><td>₱{{ number_format($yearFinancial['endingBalanceCents'] / 100, 2) }}</td></tr>
                                <tr><th scope="row">Less: Outstanding payables</th><td>Still unpaid, recorded by {{ $yearFinancial['throughDate']->format('M j, Y') }}</td><td>₱{{ number_format($yearFinancial['outstandingPayablesCents'] / 100, 2) }}</td></tr>
                                @forelse($yearFinancial['outstandingPayables'] as $payable)
                                    <tr class="statistics-disbursement-item"><th scope="row">{{ $payable->payee }}</th><td>{{ $payable->particulars }}<br><small>{{ $payable->due_date ? 'Due '.$payable->due_date->format('M j, Y') : 'No due date' }}</small></td><td>₱{{ number_format((float) $payable->amount, 2) }}</td></tr>
                                @empty
                                    <tr><td colspan="3">No outstanding payables.</td></tr>
                                @endforelse
                                <tr class="statistics-current-balance"><th scope="row">Available balance</th><td>Ending actual balance less outstanding payables</td><td>₱{{ number_format($yearFinancial['availableBalanceCents'] / 100, 2) }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <h4 class="statistics-cycle-title">Payments by collection cycle</h4>
                    <div class="statistics-table-scroll" tabindex="0" role="region" aria-label="Payments by collection cycle">
                        <table>
                            <thead><tr><th scope="col">Cycle</th><th scope="col">Payment records</th><th scope="col">Amount received</th></tr></thead>
                            <tbody>
                                @forelse($yearFinancial['paymentItems'] as $item)
                                    <tr><th scope="row">{{ $item['cycle'] }}</th><td>{{ number_format($item['records']) }}</td><td>₱{{ number_format($item['cents'] / 100, 2) }}</td></tr>
                                @empty
                                    <tr><td colspan="3">No payments in the selected years.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <h4 class="statistics-cycle-title">Disbursements in selected years</h4>
                    <div class="statistics-table-scroll" tabindex="0" role="region" aria-label="Selected year disbursements">
                        <table>
                            <thead><tr><th scope="col">Date</th><th scope="col">Payee</th><th scope="col">Category</th><th scope="col">Particulars</th><th scope="col">Amount</th></tr></thead>
                            <tbody>
                                @forelse($yearFinancial['disbursementItems'] as $item)
                                    <tr><td>{{ $item->disbursement_date->format('M j, Y') }}</td><td>{{ $item->payee }}</td><td>{{ $item->category }}</td><td>{{ $item->particulars }}</td><td>₱{{ number_format((float) $item->amount, 2) }}</td></tr>
                                @empty
                                    <tr><td colspan="5">No disbursements in the selected years.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
            @if($financialPosition)
                <section class="statistics-cycle-summary statistics-financial-report" aria-labelledby="financial-position-heading">
                    <h3 id="financial-position-heading" class="statistics-cycle-title">Current Dayong financial position</h3>
                    <p>{{ $financialPosition['periodStart'] ? 'Reporting period: '.$financialPosition['periodStart']->format('F j, Y').' to present. The beginning balance is carried forward from the last closed Claims period.' : 'Reporting period: beginning of records to present.' }}</p>
                    <div class="statistics-table-scroll" tabindex="0" role="region" aria-label="Current Dayong financial position">
                        <table>
                            <thead><tr><th scope="col">Financial item</th><th scope="col">Calculation</th><th scope="col">Amount</th></tr></thead>
                            <tbody>
                                <tr>
                                    <th scope="row">Beginning balance</th>
                                    <td>{{ $financialPosition['closure'] ? 'Closing balance carried forward from '.$financialPosition['closure']->disbursement_date->format('F j, Y') : 'Initial bank deposit' }}</td>
                                    <td>₱{{ number_format($financialPosition['beginningBalanceCents'] / 100, 2) }}</td>
                                </tr>
                                <tr>
                                    <th scope="row">Member collections</th>
                                    <td>Collections recorded in this reporting period</td>
                                    <td>₱{{ number_format($financialPosition['collectionCents'] / 100, 2) }}</td>
                                </tr>
                                <tr class="statistics-funds-total">
                                    <th scope="row">Total funds</th>
                                    <td>Beginning balance + member collections</td>
                                    <td>₱{{ number_format($financialPosition['totalFundsCents'] / 100, 2) }}</td>
                                </tr>
                                @forelse($financialPosition['disbursementItems'] as $item)
                                    <tr class="statistics-disbursement-item">
                                        <th scope="row">Less: {{ $item['category'] }}</th>
                                        <td>{{ number_format($item['recordCount']) }} {{ $item['recordCount'] === 1 ? 'disbursement' : 'disbursements' }}</td>
                                        <td>₱{{ number_format($item['cents'] / 100, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr class="statistics-disbursement-item">
                                        <th scope="row">Less: Disbursements</th><td>No recorded disbursements</td><td>₱0.00</td>
                                    </tr>
                                @endforelse
                                <tr class="statistics-disbursement-total">
                                    <th scope="row">Total disbursements</th>
                                    <td>{{ number_format($financialPosition['disbursementItems']->sum('recordCount')) }} recorded</td>
                                    <td>₱{{ number_format($financialPosition['disbursementCents'] / 100, 2) }}</td>
                                </tr>
                                <tr class="statistics-current-balance">
                                    <th scope="row">Current Dayong Actual balance</th>
                                    <td>Total funds − disbursements</td>
                                    <td>₱{{ number_format($financialPosition['currentDayongBalanceCents'] / 100, 2) }}</td>
                                </tr>
                                <tr><th scope="row">Less: Outstanding payables</th><td>Unpaid commitments across all periods</td><td>₱{{ number_format($financialPosition['outstandingPayablesCents'] / 100, 2) }}</td></tr>
                                @forelse($financialPosition['outstandingPayables'] as $payable)
                                    <tr><th scope="row">{{ $payable->payee }}</th><td>{{ $payable->particulars }}<br><small>{{ $payable->due_date ? 'Due '.$payable->due_date->format('M j, Y') : 'No due date' }}</small></td><td>₱{{ number_format((float) $payable->amount, 2) }}</td></tr>
                                @empty
                                    <tr><td colspan="3">No outstanding payables.</td></tr>
                                @endforelse
                                <tr class="statistics-current-balance"><th scope="row">Available balance</th><td>Current Dayong Actual balance less outstanding payables</td><td>₱{{ number_format($financialPosition['availableBalanceCents'] / 100, 2) }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </div>
        <p class="statistics-explanation">Each member is counted once across the selected cycles, using their current council and including all member statuses. Paid means a positive payment dated within the selected range in every applicable cycle; partial payments count as paid. Unpaid means no qualifying payment in at least one applicable cycle. Members who started after all selected cycles are excluded entirely. Payments without a date are excluded when a date range is set. Payment records may include ₱0 entries, so their count can exceed paid members. Amount paid is the sum recorded for applicable selected cycles and payment dates.</p>
    </div>
</x-filament-panels::page>
