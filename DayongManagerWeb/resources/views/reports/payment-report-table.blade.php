<table class="payment-report-table">
    <thead><tr>@foreach($indices as $index)<th scope="col">{{ $report['headers'][$index] }}</th>@endforeach</tr></thead>
    <tbody>
        @forelse($report['rows'] as $row)
            <tr>@foreach($indices as $index)<td class="{{ $index >= 2 ? 'amount' : '' }}">{{ $row[$index] }}</td>@endforeach</tr>
        @empty
            <tr><td colspan="{{ count($indices) }}">No members match the selected filters.</td></tr>
        @endforelse
        <tr class="totals">@foreach($indices as $index)<td class="{{ $index >= 2 ? 'amount' : '' }}">{{ $report['totals'][$index] }}</td>@endforeach</tr>
    </tbody>
</table>
