<x-filament-widgets::widget>
    <div class="dayong-activity-grid">
        <section class="dayong-card">
            <header class="dayong-card-heading"><div><p class="dayong-eyebrow">COLLECTIONS</p><h2>Recent payments</h2></div><a href="{{ \App\Filament\Resources\Payments\PaymentResource::getUrl() }}">View all <span aria-hidden="true">↗</span></a></header>
            <div class="dayong-payment-list">
                @forelse ($payments as $payment)
                    <div class="dayong-payment-row">
                        <span class="dayong-avatar" aria-hidden="true">{{ mb_substr($payment->member?->first_name ?? '?', 0, 1) }}{{ mb_substr($payment->member?->last_name ?? '', 0, 1) }}</span>
                        <div class="dayong-payment-person"><strong>{{ $payment->member?->full_name ?? 'Member unavailable' }}</strong><span>{{ $payment->collectionCycle?->name }}</span></div>
                        <div class="dayong-payment-value"><strong>₱{{ number_format((float) $payment->amount, 2) }}</strong><span>{{ $payment->date_paid?->format('M j, Y') ?? 'Date not recorded' }}</span></div>
                    </div>
                @empty
                    <p class="dayong-empty">Payments will appear here once they are recorded.</p>
                @endforelse
            </div>
        </section>
        <section class="dayong-card">
            <header class="dayong-card-heading"><div><p class="dayong-eyebrow">COMMUNITY SUPPORT</p><h2>Active collection cycles</h2></div></header>
            <div class="dayong-cycle-list">
                @forelse ($cycles as $cycle)
                    <div class="dayong-cycle"><span class="dayong-cycle-type">{{ $cycle->type }}</span><h3>{{ $cycle->name }}</h3><div><span>Total collected</span><strong>₱{{ number_format((float) $cycle->payments_sum_amount, 2) }}</strong></div></div>
                @empty
                    <p class="dayong-empty">Active collection cycles will appear here.</p>
                @endforelse
            </div>
        </section>
    </div>
    <footer class="dayong-footer"><span>KCLDA Dayong Manager</span><span>Charity · Unity · Fraternity · Patriotism</span></footer>
</x-filament-widgets::widget>
