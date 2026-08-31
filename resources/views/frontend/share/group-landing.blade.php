{{-- Public-safe Group short-link landing (no supplier cost / private notes). --}}
@extends(client_view('layouts.frontend', 'frontend'))

@section('title', ($title ?? 'Group offer').' — JetPakistan')

@section('content')
<div class="mx-auto max-w-lg px-4 py-12 sm:py-16">
    <p class="text-center text-sm font-semibold uppercase tracking-wide text-jp-muted">JetPakistan</p>
    <h1 class="mt-2 text-center text-2xl font-semibold text-jp-text">{{ $title ?? 'Group seats' }}</h1>
    <p class="mt-3 text-center text-jp-muted">
        Shared group reference. Availability and fare are revalidated before booking.
    </p>

    <div class="mt-8 rounded-jp-card border border-jp-border bg-jp-surface p-5 shadow-jp-card">
        <dl class="space-y-3 text-sm">
            @if (! empty($routeLabel))
                <div class="flex justify-between gap-4">
                    <dt class="text-jp-muted">Route</dt>
                    <dd class="font-medium text-jp-text text-right">{{ $routeLabel }}</dd>
                </div>
            @endif
            @if (! empty($departLabel))
                <div class="flex justify-between gap-4">
                    <dt class="text-jp-muted">Departure</dt>
                    <dd class="font-medium text-jp-text text-right">{{ $departLabel }}</dd>
                </div>
            @endif
            @if (! empty($seatsLabel))
                <div class="flex justify-between gap-4">
                    <dt class="text-jp-muted">Availability</dt>
                    <dd class="font-medium text-jp-text text-right">{{ $seatsLabel }}</dd>
                </div>
            @endif
            @if ($displayFare !== null)
                <div class="flex justify-between gap-4 border-t border-jp-border-soft pt-3">
                    <dt class="text-jp-muted">Indicative fare</dt>
                    <dd class="text-lg font-semibold text-jp-text text-right">{{ $currency }} {{ number_format((float) $displayFare) }}</dd>
                </div>
            @endif
            @if (! empty($expiresLabel))
                <div class="flex justify-between gap-4">
                    <dt class="text-jp-muted">Link expires</dt>
                    <dd class="text-jp-text text-right">{{ $expiresLabel }}</dd>
                </div>
            @endif
        </dl>
        <p class="mt-4 text-xs text-jp-muted">Prices and seats change. JetPakistan revalidates before you confirm.</p>
        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-center">
            <a href="{{ $continueUrl }}" class="inline-flex justify-center rounded-jp-button bg-jp-brand px-4 py-3 text-center font-semibold text-white">View &amp; continue</a>
            <a href="{{ $groupsUrl }}" class="inline-flex justify-center rounded-jp-button border border-jp-border px-4 py-3 text-center font-medium text-jp-text">Browse all groups</a>
        </div>
    </div>
</div>
@endsection
