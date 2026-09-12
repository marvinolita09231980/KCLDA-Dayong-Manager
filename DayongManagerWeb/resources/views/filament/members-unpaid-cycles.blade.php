@if (auth()->user()?->hasPermission('collections.view'))
    <fieldset class="members-unpaid-header">
        <legend>Unpaid collection cycles</legend>
        <p>Check cycles to show members with no payment or a zero payment. Cycles before their starting cycle and partial payments do not count as unpaid.</p>
        <fieldset class="members-unpaid-options">
            <legend>Match selected cycles</legend>
            <label><input type="radio" wire:model.live="unpaidCycleMatch" value="or"><span>OR — Unpaid in any selected cycle</span></label>
            <label><input type="radio" wire:model.live="unpaidCycleMatch" value="and"><span>AND — Unpaid in every selected cycle</span></label>
        </fieldset>
        <div class="members-unpaid-options">
            @forelse ($cycles as $cycle)
                <label wire:key="unpaid-cycle-{{ $cycle->id }}">
                    <input type="checkbox" wire:model.live="unpaidCycleIds" value="{{ $cycle->id }}">
                    <span>{{ $cycle->name }}</span>
                </label>
            @empty
                <span>No collection cycles available.</span>
            @endforelse
        </div>
        @if (count($this->unpaidCycleIds))
            <x-filament::button color="gray" size="sm" wire:click="clearUnpaidCycles">Clear cycle selection ({{ count($this->unpaidCycleIds) }})</x-filament::button>
        @endif
    </fieldset>
@endif
