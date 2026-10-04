@php($council = $getRecord())
<div class="council-cycle-table" tabindex="0" role="region" aria-label="Council {{ $council['council'] }} collection statistics">
    <table>
        <caption>Council {{ $council['council'] }}</caption>
        <thead>
            <tr><th scope="col">Council / Cycle</th><th scope="col">Members</th><th scope="col">Paid members</th><th scope="col">Unpaid members</th><th scope="col">Payment records</th><th scope="col">Zero amount records</th><th scope="col">Amount paid</th></tr>
        </thead>
        <tbody>
            @foreach($council['cycles'] as $cycle)
                <tr class="{{ $loop->first ? 'council-summary-row' : 'council-cycle-row' }}">
                    <th scope="row">@if($loop->first)Council {{ $council['council'] }} — @endif{{ $cycle['name'] }}</th>
                    <td>{{ number_format($cycle['paid'] + $cycle['unpaid']) }}</td>
                    <td>{{ number_format($cycle['paid']) }}</td><td>{{ number_format($cycle['unpaid']) }}</td>
                    <td>{{ number_format($cycle['payment_records']) }}</td><td>{{ number_format($cycle['zero_payment_records']) }}</td>
                    <td>₱{{ number_format($cycle['amount_cents'] / 100, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
