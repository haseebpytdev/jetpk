@extends(client_view('layouts.frontend', 'frontend'))

@section('title', 'Fare reference expired — JetPakistan')

@section('content')
<div class="mx-auto max-w-lg px-4 py-16 text-center">
    <p class="text-sm font-semibold uppercase tracking-wide text-jp-muted">JetPakistan</p>
    <h1 class="mt-2 text-2xl font-semibold text-jp-text">This fare reference has expired.</h1>
    <p class="mt-3 text-jp-muted">
        @if($displayFare)
            Previously shown: {{ $currency }} {{ number_format((float) $displayFare, 0) }}.
        @endif
        Fare and availability will be revalidated before booking.
    </p>
    <div class="mt-8 flex flex-wrap justify-center gap-3">
        <a href="{{ $resultsUrl }}" class="rounded-jp-button bg-jp-brand px-4 py-2 font-semibold text-white">View current fares</a>
        <a href="{{ url('/#flight-search') }}" class="rounded-jp-button border border-jp-border px-4 py-2 font-semibold text-jp-text">New search</a>
    </div>
</div>
@endsection
