@php
    $section = $whyChooseUsSection ?? [];
    $bullets = is_array($section['items'] ?? null) ? $section['items'] : [];
    $enabled = $section['enabled'] ?? true;

    if ($bullets === []) {
        $bullets = [
            ['icon_class' => 'fa-life-ring', 'title' => 'Reliable booking support', 'text' => 'Get help from a team that understands travel urgency and agency workflows.'],
            ['icon_class' => 'fa-list-alt', 'title' => 'Clear fare details', 'text' => 'See baggage, refundability, and route context before you commit to a booking.'],
            ['icon_class' => 'fa-bolt', 'title' => 'Fast booking updates', 'text' => 'Track requests and confirmations without digging through cluttered screens.'],
            ['icon_class' => 'fa-users', 'title' => 'Built for travelers and agents', 'text' => 'One platform tuned for public search and professional agency booking.'],
        ];
    }

    $displayBrand = $brandName ?? \App\Support\Branding\BrandDisplayResolver::displayName($agencySettings ?? null, auth()->user());
@endphp
@if ($enabled && $bullets !== [])
<section class="ota-v2-section ota-v2-why-section" id="why" data-testid="v2-why-book">
    <div class="ota-v2-page-wrap">
        <header class="ota-v2-section__head ota-v2-section__head--center">
            <p class="ota-v2-label">Why book with us</p>
            <h2 class="ota-v2-section-title">{{ (string) ($section['title'] ?? 'Travel booking made simple with '.$displayBrand) }}</h2>
            <p class="ota-v2-section__desc">{{ (string) ($section['subtitle'] ?? 'Search fares, submit booking requests, and get support from a team that understands your travel needs.') }}</p>
        </header>
        <div class="ota-v2-why-grid">
            @foreach ($bullets as $bullet)
                <article class="ota-v2-why-card">
                    <span class="ota-v2-why-card__icon" aria-hidden="true">
                        <i class="fa {{ $bullet['icon_class'] ?? 'fa-shield' }}"></i>
                    </span>
                    <div class="ota-v2-why-card__body">
                        <h3 class="ota-v2-why-card__title">{{ (string) ($bullet['title'] ?? '') }}</h3>
                        <p class="ota-v2-why-card__text">{{ (string) ($bullet['text'] ?? '') }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif
