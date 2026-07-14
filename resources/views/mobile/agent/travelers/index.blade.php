@extends('layouts.mobile-app')

@section('title', 'Travelers')

@section('mobile_app_title', 'Travelers')

@section('mobile_app_top_actions')
    <a href="{{ route('agent.travelers.create') }}" class="ota-mobile-app__top-action" data-testid="ota-mobile-agent-travelers-create-link">Add</a>
@endsection

@section('content')
    <div class="ota-mobile-agent" data-testid="ota-mobile-agent-travelers-index">
        @if (session('status') === 'traveler-saved')
            @include('mobile.components.alert', ['type' => 'success', 'message' => 'Traveler profile saved.'])
        @endif
        @if (session('status') === 'traveler-deleted')
            @include('mobile.components.alert', ['type' => 'success', 'message' => 'Traveler profile removed.'])
        @endif

        @if ($travelers->isEmpty())
            <div class="ota-mobile-agent__empty" data-testid="ota-mobile-agent-travelers-empty">
                <p class="ota-mobile-agent__empty-title">No travelers yet</p>
                <p class="ota-mobile-agent__empty-help">Add passenger profiles for faster booking requests.</p>
                <a href="{{ route('agent.travelers.create') }}" class="ota-mobile-agent__btn ota-mobile-agent__btn--primary">Add traveler</a>
            </div>
        @else
            <div class="ota-mobile-agent__list">
                @foreach ($travelers as $traveler)
                    <article class="ota-mobile-agent__card ota-mobile-agent__traveler-card" data-testid="ota-mobile-agent-traveler-{{ $traveler->id }}">
                        <div class="ota-mobile-agent__card-head">
                            <div>
                                <h2 class="ota-mobile-agent__card-title">{{ $traveler->fullName() }}</h2>
                                <p class="ota-mobile-agent__muted">{{ $traveler->nationality ?? 'Nationality not set' }}</p>
                            </div>
                            <span class="ota-mobile-agent__pill {{ $traveler->isComplete() ? 'ota-mobile-agent__pill--positive' : 'ota-mobile-agent__pill--pending' }}">
                                {{ $traveler->isComplete() ? 'Complete' : 'Incomplete' }}
                            </span>
                        </div>
                        <dl class="ota-mobile-agent__meta">
                            <div>
                                <dt>Document</dt>
                                <dd>{{ $traveler->maskedDocumentNumber() ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt>Expiry</dt>
                                <dd><x-dashboard.status-badge :status="$traveler->documentExpiryStatus()" /></dd>
                            </div>
                        </dl>
                        @unless ($traveler->isComplete())
                            <p class="ota-mobile-agent__note" data-testid="traveler-completeness-warning-{{ $traveler->id }}">Complete this traveler to speed up checkout.</p>
                        @endunless
                        <div class="ota-mobile-agent__actions">
                            <a href="{{ route('agent.travelers.edit', $traveler) }}" class="ota-mobile-agent__btn ota-mobile-agent__btn--secondary">Edit</a>
                            <form method="post" action="{{ route('agent.travelers.destroy', $traveler) }}" onsubmit="return confirm('Remove this traveler profile?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ota-mobile-agent__btn ota-mobile-agent__btn--danger">Delete</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
            @if ($travelers->hasPages())
                <div class="ota-mobile-agent__pagination">{{ $travelers->links() }}</div>
            @endif
        @endif
    </div>
@endsection
