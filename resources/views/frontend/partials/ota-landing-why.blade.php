@php
    $section = $whyChooseUsSection ?? [];
    $bullets = is_array($section['items'] ?? null) ? $section['items'] : [];
@endphp
<section class="ota-section ota-why-section" id="why">
    <div class="ota-container">
        <header class="ota-section-head">
            <p class="ota-section-kicker">Why book with us</p>
            <h2 class="ota-section-title">{{ (string) ($section['title'] ?? 'Travel booking made simple with '.$brandName) }}</h2>
            <p class="ota-section-desc">{{ (string) ($section['subtitle'] ?? 'Search fares, submit booking requests, and get support from a team that understands your travel needs.') }}</p>
        </header>
        <div class="ota-why-grid">
            @foreach ($bullets as $bullet)
                <div class="ota-why-item">
                    <span class="ota-why-item__icon" aria-hidden="true"><i class="fa {{ $bullet['icon_class'] ?? 'fa-shield' }}"></i></span>
                    <div>
                        <h4>{{ (string) ($bullet['title'] ?? '') }}</h4>
                        <p>{{ (string) ($bullet['text'] ?? '') }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
