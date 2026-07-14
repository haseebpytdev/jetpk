@php
    $section = $trustMetricsSection ?? [];
    $metrics = is_array($section['items'] ?? null) ? $section['items'] : [];
    $enabled = $section['enabled'] ?? true;

    if ($metrics === []) {
        $metrics = [
            ['icon_class' => 'fa-headphones', 'value' => '24/7', 'label' => 'Support when you need it'],
            ['icon_class' => 'fa-list-alt', 'value' => 'Clear', 'label' => 'Transparent fare details'],
            ['icon_class' => 'fa-refresh', 'value' => 'Flexible', 'label' => 'Booking options that adapt'],
            ['icon_class' => 'fa-shield', 'value' => 'Trusted', 'label' => 'Secure travel platform'],
        ];
    }
@endphp
@if ($enabled && $metrics !== [])
<section class="ota-v2-section ota-v2-trust-section" id="metrics" aria-label="Trust assurances" data-testid="v2-trust-strip">
    <div class="ota-v2-page-wrap">
        <div class="ota-v2-trust-grid">
            @foreach ($metrics as $metric)
                <article class="ota-v2-trust-card">
                    <span class="ota-v2-trust-card__icon" aria-hidden="true">
                        <i class="fa {{ $metric['icon_class'] ?? 'fa-check-circle' }}"></i>
                    </span>
                    <div class="ota-v2-trust-card__body">
                        <strong class="ota-v2-trust-card__title">{{ (string) ($metric['value'] ?? '') }}</strong>
                        <span class="ota-v2-trust-card__text">{{ (string) ($metric['label'] ?? '') }}</span>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif
