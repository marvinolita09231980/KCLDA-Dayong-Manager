@php
    $sections = [
        'Personal information' => ['Birth date' => $member->birth_date, 'Contact number' => $member->contact_number, 'Address' => $member->address],
        'Membership' => ['Membership type' => $member->membership_type, 'Registration date' => $member->registration_date, 'Starting cycle' => $member->startCycle?->name, 'Sponsor' => $member->sponsor_name, 'Fourth degree' => $member->is_fourth_degree],
        'Beneficiary and claims' => ['Beneficiary' => $member->beneficiary_name, 'Beneficiary contact' => $member->beneficiary_contact, 'Date of death' => $member->date_of_death, 'Service date' => $member->service_date, 'Claim received date' => $member->claim_received_date, 'Claim received by' => $member->claim_received_by, 'Claimed benefits' => $member->claimed_benefits],
    ];
@endphp
<div class="member-detail">
    <header class="member-detail-header">
        <div class="member-detail-avatar" aria-hidden="true">{{ mb_substr($member->first_name, 0, 1) }}{{ mb_substr($member->last_name, 0, 1) }}</div>
        <div class="member-detail-identity"><h2>{{ $member->full_name }}</h2><p>{{ $member->council ?: 'No council recorded' }}</p></div>
        <x-filament::badge :color="match ($member->member_status) { 'Active' => 'success', 'Inactive' => 'warning', 'Expelled' => 'danger', default => 'gray' }">{{ $member->member_status ?: 'Not specified' }}</x-filament::badge>
    </header>

    @foreach ($sections as $heading => $details)
        <section class="member-detail-section">
            <h3>{{ $heading }}</h3>
            <dl class="member-detail-grid">
                @foreach ($details as $label => $value)
                    <div><dt>{{ $label }}</dt><dd>@if (is_bool($value)){{ $value ? 'Yes' : 'No' }}@elseif ($value instanceof \DateTimeInterface){{ $value->format('M j, Y') }}@else{{ filled($value) ? $value : '—' }}@endif</dd></div>
                @endforeach
            </dl>
        </section>
    @endforeach

    @if (filled($member->remarks))
        <section class="member-detail-section"><h3>Remarks</h3><p class="member-detail-notes">{{ $member->remarks }}</p></section>
    @endif

    @if ($payments !== null)
        <section class="member-detail-section">
            <div class="payment-history-summary">
                <div><h3>Payment history</h3><p>{{ $payments->count() }} {{ \Illuminate\Support\Str::plural('payment', $payments->count()) }} recorded</p></div>
                <div class="member-detail-total"><small>Total paid</small><strong>₱{{ number_format((float) $payments->sum('amount'), 2) }}</strong></div>
            </div>
            @if ($payments->isEmpty())
                <div class="payment-history-empty">No payments recorded for this member.</div>
            @else
                <div class="payment-history-scroll member-detail-payments" tabindex="0" role="region" aria-label="Member payment history">
                    <table class="payment-history-table">
                        <caption class="sr-only">Payments for {{ $member->full_name }}</caption>
                        <thead><tr><th scope="col">Date paid</th><th scope="col">Collection cycle</th><th scope="col">Receipt</th><th scope="col">Notes</th><th scope="col" class="payment-history-amount">Amount</th></tr></thead>
                        <tbody>
                            @foreach ($payments as $payment)
                                <tr>
                                    <td data-label="Date paid">{{ $payment->date_paid?->format('M j, Y') ?? '—' }}</td>
                                    <td data-label="Collection cycle">{{ $payment->collectionCycle?->name ?? '—' }}</td>
                                    <td data-label="Receipt">{{ $payment->receipt_number ?: '—' }}</td>
                                    <td data-label="Notes" class="member-detail-payment-notes">{{ $payment->notes ?: '—' }}</td>
                                    <td data-label="Amount" class="payment-history-amount">₱{{ number_format((float) $payment->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif
    <footer class="member-detail-footer">Created {{ $member->created_at?->format('M j, Y') ?? '—' }} <span>Updated {{ $member->updated_at?->format('M j, Y') ?? '—' }}</span></footer>
</div>
