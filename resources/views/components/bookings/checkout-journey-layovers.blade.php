@props(['journey' => null])

@php
    $journey = is_array($journey ?? null) ? $journey : [];
    $segments = is_array($journey['segments_display'] ?? null) ? $journey['segments_display'] : [];
    $layovers = is_array($journey['layovers_display'] ?? null) ? $journey['layovers_display'] : [];
    $layoverSummary = is_array($journey['layover_summary'] ?? null) ? array_values(array_filter($journey['layover_summary'])) : [];
    $connectionUnavailable = (bool) ($journey['connection_details_unavailable'] ?? false);

    $routePath = '';
    if ($segments !== []) {
        $codes = [];
        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $origin = strtoupper(trim((string) ($segment['origin'] ?? '')));
            $destination = strtoupper(trim((string) ($segment['destination'] ?? '')));
            if ($origin !== '' && ($codes === [] || end($codes) !== $origin)) {
                $codes[] = $origin;
            }
            if ($destination !== '') {
                $codes[] = $destination;
            }
        }
        $routePath = implode(' → ', array_values(array_unique($codes)));
    }
    if ($routePath === '') {
        $origin = strtoupper(trim((string) ($journey['origin'] ?? '')));
        $destination = strtoupper(trim((string) ($journey['destination'] ?? '')));
        if ($origin !== '' && $destination !== '') {
            $routePath = $origin.' → '.$destination;
        }
    }

    $stopsDisplay = trim((string) ($journey['stops_display'] ?? ''));
    if ($stopsDisplay === '' && isset($journey['stops_count'])) {
        $stopsCount = (int) $journey['stops_count'];
        $stopsDisplay = $stopsCount <= 0 ? __('Direct') : ($stopsCount === 1 ? __('1 stop') : $stopsCount.' '.__('stops'));
    }

    $durationDisplay = trim((string) ($journey['duration_display'] ?? ''));
@endphp

@if ($routePath !== '' || $stopsDisplay !== '' || $durationDisplay !== '' || $layovers !== [] || $layoverSummary !== [])
    <div class="ota-checkout-journey-layovers">
        @if ($routePath !== '')
            <p class="ota-checkout-journey-layovers__route">{{ $routePath }}</p>
        @endif
        @if ($stopsDisplay !== '' || $durationDisplay !== '')
            <p class="ota-checkout-journey-layovers__meta">
                @if ($stopsDisplay !== '')
                    <span>{{ $stopsDisplay }}</span>
                @endif
                @if ($durationDisplay !== '')
                    @if ($stopsDisplay !== '')<span aria-hidden="true"> · </span>@endif
                    <span>{{ $durationDisplay }}</span>
                @endif
            </p>
        @endif
        @if (! $connectionUnavailable)
            @if ($layovers !== [])
                <ul class="ota-checkout-journey-layovers__list">
                    @foreach ($layovers as $layover)
                        @if (is_array($layover) && trim((string) ($layover['label'] ?? '')) !== '')
                            <li>{{ $layover['label'] }}</li>
                        @endif
                    @endforeach
                </ul>
            @elseif ($layoverSummary !== [])
                <ul class="ota-checkout-journey-layovers__list">
                    @foreach ($layoverSummary as $line)
                        @if (trim((string) $line) !== '')
                            <li>{{ $line }}</li>
                        @endif
                    @endforeach
                </ul>
            @endif
        @endif
    </div>
@endif
