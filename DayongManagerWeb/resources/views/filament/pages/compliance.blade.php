<x-filament-panels::page>
    <link rel="stylesheet" href="{{ asset('css/compliance.css') }}?v={{ filemtime(public_path('css/compliance.css')) }}">
    @php
        $good = $rows->where('standing', 'Yes')->count();
        $review = $rows->whereIn('recommendation', ['Review for Inactive status', 'Subject for Board expulsion review'])->count();
        $canReview = \App\Filament\Resources\Members\MemberResource::canEdit(null);
        $filtered = $search !== '' || $council !== '' || $recommendation !== '';
    @endphp
    <div class="compliance-page">
        <section class="compliance-intro" aria-label="Compliance overview">
            <div class="compliance-intro-copy">
                <span class="compliance-eyebrow">MEMBERSHIP HEALTH</span>
                <h2>A clear view of every member’s standing.</h2>
                <p>Monitor annual dues and mortuary contributions, identify outstanding obligations, and make informed membership reviews.</p>
            </div>
            <div class="compliance-date"><x-heroicon-o-shield-check aria-hidden="true" /><span>Standing as of<strong>{{ now()->format('F j, Y') }}</strong></span></div>
        </section>

        <section class="compliance-stats" aria-label="Summary of filtered members" aria-live="polite">
            @foreach([
                ['label' => 'Members in view', 'value' => $rows->count(), 'note' => $filtered ? 'Matching your filters' : 'Across all councils', 'icon' => 'heroicon-o-users', 'tone' => 'navy'],
                ['label' => 'In good standing', 'value' => $good, 'note' => 'Active and compliant', 'icon' => 'heroicon-o-check-badge', 'tone' => 'green'],
                ['label' => 'Needs review', 'value' => $review, 'note' => 'Recommended for officer review', 'icon' => 'heroicon-o-clipboard-document-check', 'tone' => 'amber'],
                ['label' => 'Not in good standing', 'value' => $rows->where('standing', 'No')->count(), 'note' => 'Outstanding dues or member status', 'icon' => 'heroicon-o-exclamation-circle', 'tone' => 'rose'],
            ] as $stat)
                <article class="compliance-stat compliance-tone-{{ $stat['tone'] }}">
                    <div><p>{{ $stat['label'] }}</p><strong>{{ number_format($stat['value']) }}</strong><span>{{ $stat['note'] }}</span></div>
                    <span class="compliance-stat-icon"><x-dynamic-component :component="$stat['icon']" aria-hidden="true" /></span>
                </article>
            @endforeach
        </section>

        <section class="compliance-directory" aria-labelledby="compliance-directory-title">
            <div class="compliance-section-heading"><div><h2 id="compliance-directory-title">Member standing</h2><p>Find a member or narrow the review by council and recommendation.</p></div><span class="compliance-count">{{ number_format($rows->count()) }} members</span></div>
            <div class="compliance-filters">
                <label class="compliance-search" for="compliance-search"><span>Search members</span><div class="compliance-input-icon"><x-heroicon-o-magnifying-glass aria-hidden="true" /><input id="compliance-search" type="search" placeholder="Search by member name…" wire:model.live.debounce.300ms="search" /></div></label>
                <label for="compliance-council"><span>Council</span><select id="compliance-council" wire:model.live="council"><option value="">All councils</option>@foreach($councils as $name)<option value="{{ $name }}">{{ $name }}</option>@endforeach</select></label>
                <label for="compliance-recommendation"><span>Recommendation</span><select id="compliance-recommendation" wire:model.live="recommendation"><option value="">All recommendations</option>@foreach(['No action', 'Review for Inactive status', 'Subject for Board expulsion review'] as $value)<option>{{ $value }}</option>@endforeach</select></label>
                <button class="compliance-reset" type="button" wire:click="resetFilters" @disabled(!$filtered)>Reset filters</button>
            </div>
            <div class="compliance-results" wire:loading.class="compliance-loading" wire:target="search,council,recommendation,resetFilters">
                <div class="compliance-list-heading" aria-hidden="true"><span>Member / council</span><span>Annual dues</span><span>Mortuary contributions</span><span>Recommendation</span></div>
                @forelse($rows as $row)
                    @php
                        $needsReview = in_array($row['recommendation'], ['Review for Inactive status', 'Subject for Board expulsion review']);
                        $tone = $row['recommendation'] === 'Subject for Board expulsion review' ? 'rose' : ($needsReview ? 'amber' : ($row['recommendation'] === 'No action' ? 'green' : 'navy'));
                        $recommendationLabel = $row['recommendation'] ?: 'Not applicable';
                    @endphp
                    <article class="compliance-member" wire:key="compliance-member-{{ $row['id'] }}" aria-label="{{ $row['name'] }}">
                        <div class="compliance-member-grid">
                            <div class="compliance-person"><span class="compliance-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($row['name'], 0, 1)) }}</span><div><h3>{{ $row['name'] }}</h3><p>{{ $row['council'] }}</p><span class="compliance-status">{{ $row['status'] }}</span></div></div>
                            <div class="compliance-member-detail"><span class="compliance-mobile-label">Annual dues</span><strong>{{ $row['annual'] }}</strong></div>
                            <div class="compliance-member-detail"><span class="compliance-mobile-label">Mortuary contributions</span><strong>{{ $row['unpaid'] === 'None' ? 'No unpaid cycles' : $row['unpaid'] }}</strong>@if($row['status'] !== 'Deceased')<span class="compliance-secondary">{{ $row['missed'] }} unpaid of latest 2 cycles</span>@endif</div>
                            <div class="compliance-standing"><span class="compliance-mobile-label">Recommendation</span><span class="compliance-badge compliance-tone-{{ $tone }}">@if($needsReview)<x-heroicon-o-exclamation-circle aria-hidden="true" />@elseif($row['recommendation'] === 'No action')<x-heroicon-o-check-circle aria-hidden="true" />@else<x-heroicon-o-minus-circle aria-hidden="true" />@endif{{ $recommendationLabel }}</span></div>
                        </div>
                        <div class="compliance-member-footer">
                            <div class="compliance-details-action">
                                <button type="button" class="compliance-review-link" x-on:click="$dispatch('open-modal', { id: 'member-compliance-{{ $row['id'] }}' })" aria-haspopup="dialog">
                                    <x-heroicon-o-arrow-up-right aria-hidden="true" /> View compliance details and payments
                                </button>
                                <span class="compliance-badge compliance-tone-{{ $row['standing'] === 'Yes' ? 'green' : ($row['standing'] === 'No' ? 'rose' : 'navy') }}">{{ $row['standing'] === 'Yes' ? 'Good standing' : ($row['standing'] === 'No' ? 'Not in good standing' : 'Good standing: Not applicable') }}</span>
                            </div>
                            @if($canReview)
                                <a class="compliance-review-link" href="{{ \App\Filament\Resources\Members\MemberResource::getUrl('edit', ['record' => $row['id']]) }}" aria-label="View member details for {{ $row['name'] }}">View member details <x-heroicon-o-arrow-up-right aria-hidden="true" /></a>
                                <button type="button" class="compliance-review-link" wire:click="mountAction('changeStatus', { member: {{ $row['id'] }} })" aria-haspopup="dialog" aria-label="Change status for {{ $row['name'] }}">Change status <x-heroicon-o-pencil-square aria-hidden="true" /></button>
                            @endif
                        </div>
                        <x-filament::modal id="member-compliance-{{ $row['id'] }}" width="5xl" :close-by-escaping="true" :close-button="true">
                            <x-slot name="heading">{{ $row['name'] }} — Compliance details and payments</x-slot>
                            <x-slot name="description">{{ $row['council'] }} · {{ $row['status'] }}</x-slot>
                            <div class="compliance-modal-content">
                                <span class="compliance-badge compliance-tone-{{ $tone }}">{{ $recommendationLabel }}</span>
                                <p class="compliance-modal-reason">{{ $row['reason'] }}</p>
                                @include('filament.pages.member-payment-details', ['row' => $row])
                            </div>
                            <x-slot name="footerActions">
                                <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'member-compliance-{{ $row['id'] }}' })">Close</x-filament::button>
                            </x-slot>
                        </x-filament::modal>
                    </article>
                @empty
                    <div class="compliance-empty"><x-heroicon-o-magnifying-glass aria-hidden="true" /><h3>{{ $filtered ? 'No matching members' : 'No members yet' }}</h3><p>{{ $filtered ? 'Try another name, council or recommendation to find the members you need.' : 'Member standing will appear here once members are added to the registry.' }}</p>@if($filtered)<button type="button" class="compliance-empty-reset" wire:click="resetFilters">Clear all filters</button>@endif</div>
                @endforelse
            </div>
        </section>
        <div class="compliance-note"><x-heroicon-o-information-circle aria-hidden="true" /><p><strong>Reviews support your decision.</strong> Recommendations do not automatically change member status. Record any required officer or Board action through the member review.</p></div>
    </div>
</x-filament-panels::page>
