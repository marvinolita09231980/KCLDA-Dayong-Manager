<x-filament-widgets::widget>
    <section class="dayong-welcome" aria-labelledby="dayong-welcome-title">
        <div class="dayong-welcome-copy">
            <p class="dayong-eyebrow">KNIGHTS OF COLUMBUS · KCLDA</p>
            <h2 id="dayong-welcome-title">Together in service.<br>United in care.</h2>
            <p>A clear view of your members, collections, and the support that brings our community together.</p>
            <div class="dayong-hero-actions">
                @if (\App\Filament\Resources\Members\MemberResource::canCreate())
                    <a class="dayong-button dayong-button-gold" href="{{ \App\Filament\Resources\Members\MemberResource::getUrl('create') }}"><span aria-hidden="true">+</span> Add a member</a>
                @endif
                @if (\App\Filament\Resources\Payments\PaymentResource::canCreate())
                    <a class="dayong-button dayong-button-outline" href="{{ \App\Filament\Resources\Payments\PaymentResource::getUrl('create') }}">Record a payment <span aria-hidden="true">↗</span></a>
                @endif
            </div>
        </div>
        <div class="dayong-principles" aria-label="Knights of Columbus principles">
            <span class="dayong-principles-cross" aria-hidden="true">✦</span>
            <span>CHARITY</span><span>UNITY</span><span>FRATERNITY</span><span>PATRIOTISM</span>
        </div>
    </section>
</x-filament-widgets::widget>
