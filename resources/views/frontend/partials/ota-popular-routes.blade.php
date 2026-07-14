@php
    $section = $popularRoutesSection ?? [];
    $popularRoutes = is_array($section['items'] ?? null) ? $section['items'] : [];
    $depart =
        (isset($defaultDepart) && $defaultDepart !== '')
            ? $defaultDepart
            : now()->addDays(14)->format('Y-m-d');
@endphp
<section class="ota-section ota-routes-section" id="routes">
    <div class="ota-container">
        <header class="ota-section-head">
            <p class="ota-section-kicker">Routes</p>
            <h2 class="ota-section-title">{{ (string) ($section['title'] ?? 'Popular corridors') }}</h2>
            <p class="ota-section-desc">{{ (string) ($section['subtitle'] ?? 'Quick links to search popular routes — final fare shown in PKR after you choose dates.') }}</p>
        </header>
        <div class="ota-routes-grid">
            @foreach ($popularRoutes as $route)
                @php
                    $href = trim((string) ($route['button_url'] ?? ''));
                    if ($href === '') {
                        $href = client_route('flights.results', [
                        'trip_type' => 'one_way',
                        'from' => $route['from'] ?? 'LHE',
                        'to' => $route['to'] ?? 'DXB',
                        'depart' => $depart,
                        'cabin' => 'economy',
                        'adults' => 1,
                        'children' => 0,
                        'infants' => 0,
                        ]);
                    }
                @endphp
                <a href="{{ $href }}" class="ota-route-card">
                    <strong>{{ (string) ($route['label'] ?? '') }}</strong>
                    <span>{{ (string) ($route['from'] ?? '') }} → {{ (string) ($route['to'] ?? '') }}</span>
                </a>
            @endforeach
        </div>
    </div>
</section>
