@php
    $section = $trustMetricsSection ?? [];
    $metrics = is_array($section['items'] ?? null) ? $section['items'] : [];
@endphp
<section class="ota-metrics-band" id="metrics" aria-label="Trust metrics">
    <div class="ota-container">
        <div class="metric-grid">
            @foreach ($metrics as $metric)
                <article class="metric-card">
                    <span class="metric-card-icon" aria-hidden="true"><i class="fa {{ $metric['icon_class'] ?? 'fa-check-circle' }}"></i></span>
                    <div class="metric-card-value">{{ (string) ($metric['value'] ?? '') }}</div>
                    <div class="metric-card-label">{{ (string) ($metric['label'] ?? '') }}</div>
                </article>
            @endforeach
        </div>
    </div>
</section>
