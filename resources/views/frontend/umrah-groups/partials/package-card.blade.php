@php
    /** @var \App\Data\UmrahGroupPackageData $package */
@endphp
<article class="ota-umrah-groups-card" data-testid="umrah-group-card">
    <div class="ota-umrah-groups-card__head">
        @if ($package->airline_logo_url)
            <img src="{{ e($package->airline_logo_url) }}" alt="" class="ota-umrah-groups-card__logo" loading="lazy" width="40" height="40">
        @endif
        <div>
            <h2 class="ota-umrah-groups-card__title">{{ e($package->title) }}</h2>
            @if ($package->airline)
                <p class="ota-umrah-groups-card__airline">{{ e($package->airline) }}</p>
            @endif
        </div>
        <span class="ota-umrah-groups-badge ota-umrah-groups-badge--{{ $package->availability_status === 'available' ? 'ok' : 'warn' }}">
            {{ $package->availability_status === 'available' ? 'Available' : 'Limited' }}
        </span>
    </div>

    <dl class="ota-umrah-groups-card__meta">
        @if ($package->sector)
            <div><dt>Route</dt><dd>{{ e($package->sector) }}</dd></div>
        @endif
        @if ($package->departure_date)
            <div><dt>Departure</dt><dd>{{ e($package->departure_date) }}</dd></div>
        @endif
        @if ($package->return_date)
            <div><dt>Return</dt><dd>{{ e($package->return_date) }}</dd></div>
        @endif
        @if ($package->duration_days !== null)
            <div><dt>Duration</dt><dd>{{ $package->duration_days }} days</dd></div>
        @endif
        @if ($package->seats_available > 0)
            <div><dt>Seats</dt><dd>{{ $package->seats_available }} left</dd></div>
        @endif
    </dl>

    <div class="ota-umrah-groups-card__foot">
        <p class="ota-umrah-groups-card__price">
            <span class="ota-umrah-groups-card__price-label">From</span>
            <strong>{{ number_format($package->price, 0) }} {{ e($package->currency) }}</strong>
            <span class="ota-umrah-groups-card__price-note">per adult</span>
        </p>
        <div class="ota-umrah-groups-card__actions">
            <a href="{{ client_route('umrah-groups.show', ['package' => $package->public_id]) }}" class="ota-btn ota-btn-primary ota-btn-sm">View Details</a>
            <a href="{{ client_route('support') }}" class="ota-btn ota-btn-secondary ota-btn-sm">Contact / Enquire</a>
        </div>
    </div>
</article>
