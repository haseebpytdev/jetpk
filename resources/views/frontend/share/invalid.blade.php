@extends(client_view('layouts.frontend', 'frontend'))

@section('title', 'Link unavailable — JetPakistan')

@section('content')
<div class="mx-auto max-w-lg px-4 py-16 text-center">
    <p class="text-sm font-semibold uppercase tracking-wide text-jp-muted">JetPakistan</p>
    <h1 class="mt-2 text-2xl font-semibold text-jp-text">{{ $message }}</h1>
    <div class="mt-8 flex flex-wrap justify-center gap-3">
        <a href="{{ url('/#flight-search') }}" class="rounded-jp-button bg-jp-brand px-4 py-2 font-semibold text-white">Search flights</a>
        <a href="{{ url('/groups') }}" class="rounded-jp-button border border-jp-border px-4 py-2 font-semibold text-jp-text">Browse groups</a>
    </div>
</div>
@endsection
