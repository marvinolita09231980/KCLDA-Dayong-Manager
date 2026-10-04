<x-filament::section heading="Payment filters">
    <p class="mb-4 text-sm text-gray-600">These filters choose the member list for the Paid and Unpaid reports. Select one or more cycles, or leave cycles blank for all. When downloading, choose any payment-cycle columns independently of these filters. Registration and annual dues for the same year share one column.</p>
    {{ $filtersForm }}
    <div class="mt-4">
        <x-filament::button color="gray" size="sm" wire:click="resetTableFiltersForm">Reset filters</x-filament::button>
    </div>
</x-filament::section>
