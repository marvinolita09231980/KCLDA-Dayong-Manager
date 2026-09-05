<dl class="dayong-record-details">
    @foreach($details as $label => $value)
        <div>
            <dt>{{ $label }}</dt>
            <dd>@if(is_bool($value)){{ $value ? 'Yes' : 'No' }}@elseif($value instanceof \DateTimeInterface){{ $value->format('M j, Y') }}@elseif(is_array($value) || $value instanceof \Illuminate\Support\Collection){{ collect($value)->join(', ') ?: '—' }}@else{{ filled($value) ? $value : '—' }}@endif</dd>
        </div>
    @endforeach
</dl>
