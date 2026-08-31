@extends(client_view('layouts.frontend', 'frontend'))

@section('title', 'Group reference expired — JetPakistan')

@section('content')
<div class="mx-auto max-w-lg px-4 py-16 text-center">
    <p class="text-sm font-semibold uppercase tracking-wide text-jp-muted">JetPakistan</p>
    <h1 class="mt-2 text-2xl font-semibold text-jp-text">This group reference has expired.</h1>
    <p class="mt-3 text-jp-muted">Browse current JetPakistan group seats. Availability is revalidated before booking.</p>
    <div class="mt-8">
        <a href="{{ $groupsUrl }}" class="rounded-jp-button bg-jp-brand px-4 py-2 font-semibold text-white">Browse groups</a>
    </div>
</div>
@endsection
