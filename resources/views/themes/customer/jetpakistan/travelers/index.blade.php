{{-- JP-PORTAL-3 TASK 2 · Customer travelers — index (JetPK theme)
     Resolved by client_view('travelers.index', 'customer'); dashboard.travelers.index remains the
     fallback for default/Parwaaz clients and is NOT modified.

     RECOMPOSED (not wrapped) into the JetPK vocabulary. Preserved verbatim from the legacy
     $isCustomerAccount branch of dashboard.travelers.index:
       • controller vars: $travelers, $defaultTraveler, $routePrefix ('customer.travelers')
       • section contract: account_title / account_subtitle / account_actions / account_content
       • account_title 'Travelers'; subtitle 'Manage your profile traveler and saved passengers
         for faster checkout.' (customer wording — NOT the agent wording)
       • flash: session('status') === 'traveler-saved' / 'traveler-deleted', exact copy
       • default traveler card rendered inside @isset($defaultTraveler)
       • "Additional travelers" section head + lead
       • columns: Name, Nationality, Document, Expiry, Status, Actions
       • data: fullName(), nationality ?? '—', maskedDocumentNumber() ?? '—',
         documentExpiryStatus(), isComplete()
       • <x-dashboard.status-badge> for expiry (canonical — reused)
       • DELETE form: @csrf + @method('DELETE') + confirm('Remove this traveler profile?')
       • routes: $routePrefix.'.{create,edit,destroy}' via $routePrefix (never hardcoded)
       • pagination: $travelers->links() gated by hasPages()
       • data-testids: customer-add-traveler, traveler-flash-saved, traveler-flash-deleted,
         saved-travelers-table-card, saved-travelers-table, saved-traveler-row-{id},
         saved-traveler-mobile-{id}, traveler-completeness-warning-{id}
     Desktop table + mobile card list both retained — no information hidden at any width.

     NOTE ON $canManageTravelers: the legacy view computes it as
       ! $isAgentAccount || hasAgentPermission(TravelersManage)
     which is ALWAYS true on the customer branch. It is therefore intentionally not reproduced
     here; the customer view has no permission gate to preserve. See the agent view for the
     permission-bearing variant.
--}}
@extends(client_layout('customer-account', 'customer'))

@section('title', 'Travelers')

@section('account_title', 'Travelers')
@section('account_subtitle', 'Manage your profile traveler and saved passengers for faster checkout.')

@section('account_actions')
    <a href="{{ route($routePrefix.'.create') }}" class="jp-btn jp-btn--primary" data-testid="customer-add-traveler">Add traveler</a>
@endsection

@section('account_content')
    <x-dashboard.breadcrumbs :items="[
        ['label' => 'Dashboard', 'href' => client_route('customer.dashboard')],
        ['label' => 'Travelers'],
    ]" />

    @if (session('status') === 'traveler-saved')
        <x-jp.alert variant="success" data-testid="traveler-flash-saved">Traveler profile saved.</x-jp.alert>
    @endif
    @if (session('status') === 'traveler-deleted')
        <x-jp.alert variant="success" data-testid="traveler-flash-deleted">Traveler profile removed.</x-jp.alert>
    @endif

    @isset($defaultTraveler)
        @include('themes.frontend.jetpakistan.components.portal.default-traveler-card', [
            'defaultTraveler' => $defaultTraveler,
            'routePrefix' => $routePrefix,
        ])
    @endisset

    <div class="jp-portal__section-head">
        <h2 class="jp-portal__section-title">Additional travelers</h2>
        <p class="jp-portal__section-lead">Passengers you book for besides yourself.</p>
    </div>

    @if ($travelers->isEmpty())
        <x-jp.card class="jp-portal__panel" data-testid="saved-travelers-table-card">
            <div class="jp-empty">
                <span class="jp-empty__icon" aria-hidden="true"><x-jp.icon name="users" /></span>
                <p class="jp-empty__title">No additional travelers yet</p>
                <p class="jp-empty__help">Add profiles for passengers you book often.</p>
                <a href="{{ route($routePrefix.'.create') }}" class="jp-btn jp-btn--primary">Add traveler</a>
            </div>
        </x-jp.card>
    @else
        <x-jp.card class="jp-portal__panel jp-portal__panel--flush" data-testid="saved-travelers-table-card">
            {{-- Desktop: full table. Every legacy column retained. --}}
            <div class="jp-table-wrap jp-table-wrap--desktop">
                <table class="jp-table" data-testid="saved-travelers-table">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Nationality</th>
                            <th scope="col">Document</th>
                            <th scope="col">Expiry</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="jp-table__cell--end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($travelers as $traveler)
                            <tr data-testid="saved-traveler-row-{{ $traveler->id }}">
                                <td data-label="Name">{{ $traveler->fullName() }}</td>
                                <td data-label="Nationality">{{ $traveler->nationality ?? '—' }}</td>
                                <td data-label="Document">{{ $traveler->maskedDocumentNumber() ?? '—' }}</td>
                                <td data-label="Expiry"><x-dashboard.status-badge :status="$traveler->documentExpiryStatus()" /></td>
                                <td data-label="Status">
                                    @if ($traveler->isComplete())
                                        <span class="jp-badge jp-badge--success">Complete</span>
                                    @else
                                        <span class="jp-badge jp-badge--warning">Incomplete</span>
                                        <p class="jp-field__help" data-testid="traveler-completeness-warning-{{ $traveler->id }}">Complete this traveler to speed up checkout.</p>
                                    @endif
                                </td>
                                <td data-label="Actions" class="jp-table__cell--end">
                                    <div class="jp-portal__row-actions">
                                        <a href="{{ route($routePrefix.'.edit', $traveler) }}" class="jp-btn jp-btn--ghost jp-btn--sm">Edit</a>
                                        <form method="post" action="{{ route($routePrefix.'.destroy', $traveler) }}" onsubmit="return confirm('Remove this traveler profile?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="jp-btn jp-btn--danger jp-btn--sm">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile: same data as cards — nothing dropped. --}}
            <div class="jp-portal__list jp-portal__list--mobile">
                @foreach ($travelers as $traveler)
                    <article class="jp-portal__list-card" data-testid="saved-traveler-mobile-{{ $traveler->id }}">
                        <div class="jp-portal__list-card-head">
                            <div>
                                <h3 class="jp-portal__list-card-name">{{ $traveler->fullName() }}</h3>
                                <p class="jp-portal__list-card-meta">{{ $traveler->nationality ?? 'Nationality not set' }}</p>
                            </div>
                            @if ($traveler->isComplete())
                                <span class="jp-badge jp-badge--success">Complete</span>
                            @else
                                <span class="jp-badge jp-badge--warning">Incomplete</span>
                            @endif
                        </div>
                        <div class="jp-portal__list-card-meta">
                            <span>Document: {{ $traveler->maskedDocumentNumber() ?? '—' }}</span>
                            <span>Expiry: <x-dashboard.status-badge :status="$traveler->documentExpiryStatus()" /></span>
                        </div>
                        <div class="jp-portal__list-card-actions">
                            <a href="{{ route($routePrefix.'.edit', $traveler) }}" class="jp-btn jp-btn--primary jp-btn--sm">Edit</a>
                            <form method="post" action="{{ route($routePrefix.'.destroy', $traveler) }}" onsubmit="return confirm('Remove this traveler profile?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="jp-btn jp-btn--danger jp-btn--sm">Delete</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($travelers->hasPages())
                <div class="jp-portal__pagination">{{ $travelers->links() }}</div>
            @endif
        </x-jp.card>
    @endif
@endsection
