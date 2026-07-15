@extends('layouts.mobile-app')

@section('title', 'Commission statement')

@section('mobile_app_title', 'Statement')

@section('mobile_app_back')
    <a href="{{ route('agent.commissions.index') }}" class="ota-mobile-app__back-btn" aria-label="Back to commissions">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    </a>
@endsection

@section('content')
    <div class="ota-mobile-agent" data-testid="ota-mobile-agent-commission-statement">
        <section class="ota-mobile-agent__card">
            <h2 class="ota-mobile-agent__card-title">Statement {{ $statement->statement_number ?? '#'.$statement->id }}</h2>
            <dl class="ota-mobile-agent__meta ota-mobile-agent__meta--finance">
                <div>
                    <dt>Period</dt>
                    <dd>{{ $statement->period_start?->format('Y-m-d') ?? 'N/A' }} - {{ $statement->period_end?->format('Y-m-d') ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt>Status</dt>
                    <dd>@include('mobile.agent.partials.agent-status-pill', ['status' => $statement->status->value])</dd>
                </div>
                <div>
                    <dt>Closing balance</dt>
                    <dd class="ota-mobile-agent__amount">Rs {{ number_format((float) $statement->closing_balance, 2) }}</dd>
                </div>
            </dl>
        </section>

        <section class="ota-mobile-agent__list" aria-label="Statement entries">
            <div class="ota-mobile-agent__card-head">
                <h2 class="ota-mobile-agent__card-title">Entries</h2>
            </div>
            @forelse($statement->entries as $entry)
                <article class="ota-mobile-agent__card">
                    <div class="ota-mobile-agent__card-head">
                        <span class="ota-mobile-agent__ref">{{ $entry->created_at?->format('j M Y, g:i A') ?? '—' }}</span>
                        <span class="ota-mobile-agent__pill ota-mobile-agent__pill--muted">{{ str_replace('_', ' ', $entry->type->value) }}</span>
                    </div>
                    <p class="ota-mobile-agent__text-safe">Booking: {{ $entry->booking?->booking_reference ?? 'N/A' }}</p>
                    <p class="ota-mobile-agent__amount ota-mobile-agent__amount--total">Rs {{ number_format((float) $entry->commission_amount, 2) }}</p>
                </article>
            @empty
                <div class="ota-mobile-agent__card">
                    <p class="ota-mobile-agent__note">No entries in this statement.</p>
                </div>
            @endforelse
        </section>
    </div>
@endsection
