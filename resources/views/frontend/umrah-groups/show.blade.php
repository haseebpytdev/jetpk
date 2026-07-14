@extends(client_layout('frontend', 'frontend'))

@php
    /** @var \App\Data\UmrahGroupPackageData $package */
    $brand = config('ota-brand', []);
    $client = config('ota-client', []);
    $brandName = $client['agency_name'] ?? ($brand['product_name'] ?? config('app.name'));
@endphp

@section('title', e($package->title).' — '.$brandName)

@section('content')
    <section class="ota-section ota-umrah-groups-page ota-umrah-groups-detail" aria-labelledby="ota-umrah-detail-heading">
        <div class="ota-container">
            <p class="ota-umrah-groups-back">
                <a href="{{ client_route('umrah-groups.index') }}">&larr; Back to Group Ticketing</a>
            </p>

            <header class="ota-section-head ota-umrah-groups-hero">
                <p class="ota-section-kicker">{{ e($package->package_type ?? 'Group Ticket') }}</p>
                <h1 id="ota-umrah-detail-heading" class="ota-section-title">{{ e($package->title) }}</h1>
                @if ($package->airline)
                    <p class="ota-section-desc">{{ e($package->airline) }}</p>
                @endif
            </header>

            <div class="ota-umrah-groups-detail-grid">
                <div class="ota-umrah-groups-panel">
                    <h2 class="ota-umrah-groups-panel__title">Package summary</h2>
                    <dl class="ota-umrah-groups-detail-list">
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
                        <div><dt>Availability</dt><dd>{{ $package->availability_status === 'available' ? 'Available' : 'Limited' }} ({{ $package->seats_available }} seats)</dd></div>
                        @if ($package->baggage)
                            <div><dt>Baggage</dt><dd>{{ e($package->baggage) }}</dd></div>
                        @endif
                        @if ($package->meal)
                            <div><dt>Meal</dt><dd>{{ e($package->meal) }}</dd></div>
                        @endif
                    </dl>
                </div>

                <div class="ota-umrah-groups-panel">
                    <h2 class="ota-umrah-groups-panel__title">Pricing</h2>
                    <ul class="ota-umrah-groups-pricing">
                        <li><span>Adult</span><strong>{{ number_format($package->price, 0) }} {{ e($package->currency) }}</strong></li>
                        @if ($package->price_child !== null)
                            <li><span>Child</span><strong>{{ number_format($package->price_child, 0) }} {{ e($package->currency) }}</strong></li>
                        @endif
                        @if ($package->price_infant !== null)
                            <li><span>Infant</span><strong>{{ number_format($package->price_infant, 0) }} {{ e($package->currency) }}</strong></li>
                        @endif
                    </ul>
                    <p class="ota-umrah-groups-detail-note">Prices are indicative. Final confirmation is subject to supplier availability.</p>
                    <div class="ota-umrah-groups-detail-actions">
                        <a href="{{ $enquireUrl }}" class="ota-btn ota-btn-primary">Contact / Enquire</a>
                    </div>
                </div>
            </div>

            @if (count($package->legs) > 0)
                <div class="ota-umrah-groups-panel ota-umrah-groups-legs">
                    <h2 class="ota-umrah-groups-panel__title">Flight itinerary</h2>
                    <div class="ota-r-table-wrap">
                        <table class="table ota-umrah-groups-legs-table">
                            <thead>
                                <tr>
                                    <th>Segment</th>
                                    <th>Flight</th>
                                    <th>Route</th>
                                    <th>Date</th>
                                    <th>Departure</th>
                                    <th>Arrival</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($package->legs as $leg)
                                    <tr>
                                        <td>{{ e($leg['label'] ?? 'Leg') }}</td>
                                        <td>{{ e($leg['flight_no'] ?? '—') }}</td>
                                        <td>{{ e(($leg['origin'] ?? '—').' → '.($leg['destination'] ?? '—')) }}</td>
                                        <td>{{ e($leg['date'] ?? '—') }}</td>
                                        <td>{{ e($leg['departure_time'] ?? '—') }}</td>
                                        <td>{{ e($leg['arrival_time'] ?? '—') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection
