@php
    $section = $popularRoutesSection ?? [];
    $popularRoutes = is_array($section['items'] ?? null) ? $section['items'] : [];
    $enabled = ($section['enabled'] ?? true) && $popularRoutes !== [];
    $depart = (isset($defaultDepart) && $defaultDepart !== '')
        ? $defaultDepart
        : now()->addDays(14)->format('Y-m-d');

    $preserveHref = static function (string $href): string {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            $parsed = parse_url($href);
            $path = $parsed['path'] ?? '/';
            $query = isset($parsed['query']) && $parsed['query'] !== '' ? '?'.$parsed['query'] : '';

            return url(ui_preserve_url($path.$query));
        }

        return ui_preserve_url($href);
    };
@endphp
@if ($enabled)
<section class="ota-v2-section ota-v2-route-section" id="routes" data-testid="v2-popular-corridors">
    <div class="ota-v2-page-wrap">
        <header class="ota-v2-section__head">
            <p class="ota-v2-label">Routes</p>
            <h2 class="ota-v2-section-title">{{ (string) ($section['title'] ?? 'Popular corridors') }}</h2>
            <p class="ota-v2-section__desc">{{ (string) ($section['subtitle'] ?? 'Quick links to search popular routes — final fare shown in PKR after you choose dates.') }}</p>
        </header>
        <div class="ota-v2-route-grid">
            @foreach ($popularRoutes as $route)
                @php
                    $href = trim((string) ($route['button_url'] ?? ''));
                    if ($href === '') {
                        $href = route('flights.results', [
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
                    $from = (string) ($route['from'] ?? '');
                    $to = (string) ($route['to'] ?? '');
                @endphp
                <a href="{{ $preserveHref($href) }}" class="ota-v2-route-card">
                    <strong class="ota-v2-route-card__title">{{ (string) ($route['label'] ?? $from.' → '.$to) }}</strong>
                    <span class="ota-v2-route-card__code">{{ $from }} → {{ $to }}</span>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif
