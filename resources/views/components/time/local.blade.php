@if ($hasValue)
    @if ($utcTitle !== '')
        <time datetime="{{ \Illuminate\Support\Carbon::parse($value)->utc()->toIso8601String() }}" title="{{ $utcTitle }}">{{ $label }}</time>
    @else
        <time datetime="{{ \Illuminate\Support\Carbon::parse($value)->utc()->toIso8601String() }}">{{ $label }}</time>
    @endif
@else
    {{ $empty }}
@endif
