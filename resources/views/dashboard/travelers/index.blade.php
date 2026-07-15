@php
    $isCustomerAccount = str_starts_with($routePrefix ?? '', 'customer.');
    $isAgentAccount = str_starts_with($routePrefix ?? '', 'agent.');
    $isPortalAccount = $isCustomerAccount || $isAgentAccount;
    $canManageTravelers = ! $isAgentAccount || (auth()->user()?->hasAgentPermission(\App\Support\Agents\AgentPermission::TravelersManage) ?? false);
    $portalLayout = match (true) {
        $isCustomerAccount => 'layouts.customer-account',
        $isAgentAccount => 'layouts.agent-portal',
        default => 'layouts.dashboard',
    };
@endphp
@extends($portalLayout)

@section('title', $isCustomerAccount ? 'Travelers' : ($isAgentAccount ? 'Travelers' : 'Saved travelers'))

@if ($isPortalAccount)
    @section('account_title', $isAgentAccount ? 'Saved travelers' : 'Travelers')
    @section('account_subtitle', $isAgentAccount ? 'Reuse passenger details for future bookings.' : 'Manage your profile traveler and saved passengers for faster checkout.')
    @section('account_actions')
        @if ($canManageTravelers)
            <a href="{{ route($routePrefix.'.create') }}" class="ota-account-btn ota-account-btn--primary" data-testid="customer-add-traveler">Add traveler</a>
        @endif
    @endsection
@else
    @section('page-header')
        <x-dashboard.section-header
            title="Saved travelers"
            subtitle="Reuse passenger details for future bookings."
        >
            <x-slot name="actions">
                <a href="{{ route($routePrefix.'.create') }}" class="btn btn-primary btn-sm">Add traveler</a>
            </x-slot>
        </x-dashboard.section-header>
    @endsection
@endif

@if ($isPortalAccount)
@section('account_content')
@else
@section('content')
@endif
    @if (session('status') === 'traveler-saved')
        <div class="{{ $isPortalAccount ? 'ota-account-alert ota-account-alert--success' : 'alert alert-success' }}" data-testid="traveler-flash-saved">Traveler profile saved.</div>
    @endif
    @if (session('status') === 'traveler-deleted')
        <div class="{{ $isPortalAccount ? 'ota-account-alert ota-account-alert--success' : 'alert alert-success' }}" data-testid="traveler-flash-deleted">Traveler profile removed.</div>
    @endif

    @if ($isPortalAccount)
        @if ($isCustomerAccount)
            @isset($defaultTraveler)
            @include('dashboard.customer.partials.default-traveler-card', [
                'defaultTraveler' => $defaultTraveler,
                'routePrefix' => $routePrefix,
            ])
        @endisset

            <div class="ota-account-section-head">
                <h2 class="ota-account-section-title">Additional travelers</h2>
                <p class="ota-account-section-lead">Passengers you book for besides yourself.</p>
            </div>
        @endif

        @if ($travelers->isEmpty())
            <div class="ota-account-card" data-testid="saved-travelers-table-card">
                <div class="ota-account-card__body">
                    <div class="ota-account-empty ota-account-empty--compact">
                        <div class="ota-account-empty-icon" aria-hidden="true"><i class="ti ti-users"></i></div>
                        <p class="ota-account-empty-title">No additional travelers yet</p>
                        <p class="ota-account-empty-help">Add profiles for passengers you book often.</p>
                        @if ($canManageTravelers)
                            <div class="ota-account-empty-action">
                                <a href="{{ route($routePrefix.'.create') }}" class="ota-account-btn ota-account-btn--primary">Add traveler</a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <div class="ota-account-card" data-testid="saved-travelers-table-card">
                <div class="ota-account-card__body ota-account-card__body--flush">
                    <div class="ota-account-table-wrap ota-account-table--desktop">
                        <table class="ota-account-table mb-0" data-testid="saved-travelers-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Nationality</th>
                                    <th>Document</th>
                                    <th>Expiry</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($travelers as $traveler)
                                    <tr data-testid="saved-traveler-row-{{ $traveler->id }}">
                                        <td>{{ $traveler->fullName() }}</td>
                                        <td>{{ $traveler->nationality ?? '—' }}</td>
                                        <td>{{ $traveler->maskedDocumentNumber() ?? '—' }}</td>
                                        <td><x-dashboard.status-badge :status="$traveler->documentExpiryStatus()" /></td>
                                        <td>
                                            @if ($traveler->isComplete())
                                                <span class="ota-account-badge ota-account-badge--success">Complete</span>
                                            @else
                                                <span class="ota-account-badge ota-account-badge--warning">Incomplete</span>
                                                <div class="small text-muted mt-1" data-testid="traveler-completeness-warning-{{ $traveler->id }}">Complete this traveler to speed up checkout.</div>
                                            @endif
                                        </td>
                                        <td class="text-end text-nowrap">
                                            @if ($canManageTravelers)
                                                <div class="ota-account-traveler-card__actions">
                                                    <a href="{{ route($routePrefix.'.edit', $traveler) }}" class="ota-account-btn ota-account-btn--secondary ota-account-btn--sm">Edit</a>
                                                    <form method="post" action="{{ route($routePrefix.'.destroy', $traveler) }}" class="d-inline" onsubmit="return confirm('Remove this traveler profile?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="ota-account-btn ota-account-btn--danger ota-account-btn--sm">Delete</button>
                                                    </form>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="ota-account-list ota-account-list--mobile">
                        @foreach ($travelers as $traveler)
                            <article class="ota-account-traveler-card" data-testid="saved-traveler-mobile-{{ $traveler->id }}">
                                <div class="ota-account-traveler-card__head">
                                    <div>
                                        <h3 class="ota-account-traveler-card__name">{{ $traveler->fullName() }}</h3>
                                        <p class="ota-account-traveler-card__meta">{{ $traveler->nationality ?? 'Nationality not set' }}</p>
                                    </div>
                                    @if ($traveler->isComplete())
                                        <span class="ota-account-badge ota-account-badge--success">Complete</span>
                                    @else
                                        <span class="ota-account-badge ota-account-badge--warning">Incomplete</span>
                                    @endif
                                </div>
                                @if ($canManageTravelers)
                                    <div class="ota-account-traveler-card__actions">
                                        <a href="{{ route($routePrefix.'.edit', $traveler) }}" class="ota-account-btn ota-account-btn--primary ota-account-btn--sm">Edit</a>
                                        <form method="post" action="{{ route($routePrefix.'.destroy', $traveler) }}" class="d-inline" onsubmit="return confirm('Remove this traveler profile?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ota-account-btn ota-account-btn--danger ota-account-btn--sm">Delete</button>
                                        </form>
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </div>
                @if ($travelers->hasPages())
                    <div class="ota-account-card__footer">{{ $travelers->links() }}</div>
                @endif
            </div>
        @endif
    @else
        <div class="alert alert-light border mb-4 small" data-testid="traveler-phase-note">
            Saved travelers can be used for faster booking in a later phase.
        </div>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <x-dashboard.quick-action
                    icon="ti-user-plus"
                    title="Add traveler"
                    :href="route($routePrefix.'.create')"
                    helper="Store passport and contact details securely."
                />
            </div>
            <div class="col-md-4">
                <x-dashboard.quick-action
                    icon="ti-ticket"
                    title="My bookings"
                    :href="route('agent.bookings.index')"
                    helper="Continue an existing reservation."
                />
            </div>
        </div>

        @if ($travelers->isEmpty())
            <div class="card border-0 shadow-sm" data-testid="saved-travelers-table-card">
                <div class="card-body">
                    <x-dashboard.empty-state
                        icon="ti-users"
                        title="No saved travelers yet"
                        help="Add profiles for passengers you book often."
                    >
                        <x-slot name="action">
                            <a href="{{ route($routePrefix.'.create') }}" class="btn btn-primary btn-sm">Add traveler</a>
                        </x-slot>
                    </x-dashboard.empty-state>
                </div>
            </div>
        @else
            <div class="card border-0 shadow-sm" data-testid="saved-travelers-table-card">
                <div class="table-responsive">
                    <table class="table table-vcenter card-table mb-0" data-testid="saved-travelers-table">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Nationality</th>
                                <th>Document</th>
                                <th>Document #</th>
                                <th>Expiry</th>
                                <th>Completeness</th>
                                <th class="text-end"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($travelers as $traveler)
                                <tr data-testid="saved-traveler-row-{{ $traveler->id }}">
                                    <td>
                                        {{ $traveler->fullName() }}
                                        @if ($traveler->is_default)
                                            <span class="badge bg-primary-lt ms-1">Default</span>
                                        @endif
                                    </td>
                                    <td>{{ $traveler->nationality ?? '—' }}</td>
                                    <td>{{ $traveler->document_type ? str_replace('_', ' ', $traveler->document_type) : '—' }}</td>
                                    <td data-testid="traveler-masked-doc-{{ $traveler->id }}">{{ $traveler->maskedDocumentNumber() ?? '—' }}</td>
                                    <td><x-dashboard.status-badge :status="$traveler->documentExpiryStatus()" /></td>
                                    <td>
                                        <x-dashboard.status-badge :status="$traveler->completenessStatus()" />
                                        @unless ($traveler->isComplete())
                                            <div class="small text-warning mt-1" data-testid="traveler-completeness-warning-{{ $traveler->id }}">Missing details for ticketing readiness</div>
                                        @endunless
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route($routePrefix.'.edit', $traveler) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <form method="post" action="{{ route($routePrefix.'.destroy', $traveler) }}" class="d-inline" onsubmit="return confirm('Remove this traveler profile?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($travelers->hasPages())
                    <div class="card-footer">{{ $travelers->links() }}</div>
                @endif
            </div>
        @endif
    @endif
@endsection
