@extends(client_layout('mobile-app', 'mobile'))

@section('title', 'Travelers')

@section('mobile_app_title', 'Travelers')

@section('mobile_app_top_actions')
    <a href="{{ route('customer.travelers.create') }}" class="ota-mobile-app__top-action" data-testid="ota-mobile-customer-travelers-create-link">Add</a>
@endsection

@section('content')
    <div class="ota-mobile-customer" data-testid="ota-mobile-customer-travelers-index">
        @if (session('status') === 'traveler-saved')
            @include('mobile.components.alert', ['type' => 'success', 'message' => 'Traveler profile saved.'])
        @endif
        @if (session('status') === 'traveler-deleted')
            @include('mobile.components.alert', ['type' => 'success', 'message' => 'Traveler profile removed.'])
        @endif

        @isset($defaultTraveler)
            @include('themes.frontend.jetpakistan.components.portal.default-traveler-card', [
                'defaultTraveler' => $defaultTraveler,
                'routePrefix' => $routePrefix ?? 'customer.travelers',
            ])
        @endisset

        @if ($travelers->isEmpty())
            <div class="ota-mobile-customer__empty" data-testid="ota-mobile-customer-travelers-empty">
                <p class="ota-mobile-customer__empty-title">No additional travelers yet</p>
                <p class="ota-mobile-customer__empty-help">Add profiles for passengers you book often.</p>
                <a href="{{ route('customer.travelers.create') }}" class="ota-mobile-customer__btn ota-mobile-customer__btn--primary">Add traveler</a>
            </div>
        @else
            <div class="ota-mobile-customer__list">
                @foreach ($travelers as $traveler)
                    <article class="ota-mobile-customer__card" data-testid="ota-mobile-customer-traveler-{{ $traveler->id }}">
                        <div class="ota-mobile-customer__card-head">
                            <div>
                                <h2 class="ota-mobile-customer__card-title">{{ $traveler->fullName() }}</h2>
                                <p class="ota-mobile-customer__muted">{{ $traveler->nationality ?? 'Nationality not set' }}</p>
                            </div>
                            <span class="ota-mobile-customer__pill {{ $traveler->isComplete() ? 'ota-mobile-customer__pill--positive' : 'ota-mobile-customer__pill--pending' }}">
                                {{ $traveler->isComplete() ? 'Complete' : 'Incomplete' }}
                            </span>
                        </div>
                        <dl class="ota-mobile-customer__meta">
                            <div>
                                <dt>Document</dt>
                                <dd>{{ $traveler->maskedDocumentNumber() ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt>Expiry</dt>
                                <dd><x-dashboard.status-badge :status="$traveler->documentExpiryStatus()" /></dd>
                            </div>
                        </dl>
                        <div class="ota-mobile-customer__actions">
                            <a href="{{ route('customer.travelers.edit', $traveler) }}" class="ota-mobile-customer__btn ota-mobile-customer__btn--secondary">Edit</a>
                            <form method="post" action="{{ route('customer.travelers.destroy', $traveler) }}" onsubmit="return confirm('Remove this traveler profile?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ota-mobile-customer__btn ota-mobile-customer__btn--danger">Delete</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
            @if ($travelers->hasPages())
                <div class="ota-mobile-customer__pagination">{{ $travelers->links() }}</div>
            @endif
        @endif
    </div>
@endsection
