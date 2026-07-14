@php
    $f = $footerPresentation ?? [];
    if (! ($f['is_enabled'] ?? true)) {
        return;
    }
    $style = $f['style'] ?? [];
    $brand = $f['brand'] ?? [];
    $supportCard = $f['support_card'] ?? [];
    $contact = $f['contact'] ?? [];
    $bottom = $f['bottom_bar'] ?? [];
    $spacing = $style['spacing'] ?? 'normal';
    $columns = (int) ($style['columns'] ?? 5);
    $showSupportCard = ($style['show_support_card'] ?? true) && ($supportCard['is_enabled'] ?? true);
    $showContact = $contact['is_enabled'] ?? true;
    $brandEmail = trim((string) ($contact['email'] ?? ''));
    $brandWhatsApp = trim((string) ($contact['whatsapp'] ?? ''));
    $showBrandEmail = $showContact && ($contact['show_email'] ?? true) && $brandEmail !== '';
    $showBrandWhatsApp = $showContact && ($contact['show_whatsapp'] ?? true) && $brandWhatsApp !== '';
    $supportIcon = match ($supportCard['icon'] ?? 'headphones') {
        'phone' => 'fa-phone',
        'life-ring' => 'fa-life-ring',
        default => 'fa-headphones',
    };
@endphp
<footer class="footer-copyright ota-footer-pro ota-footer-pro--{{ $spacing }} ota-footer-pro--cols-{{ $columns }}"
        style="--ota-footer-bg: {{ $style['background_color'] ?? '#F8FAFC' }};
               --ota-footer-bar-bg: {{ $style['bottom_bar_background_color'] ?? '#F1F5F9' }};
               --ota-footer-text: {{ $style['text_color'] ?? '#334155' }};
               --ota-footer-heading: {{ $style['heading_color'] ?? '#0F172A' }};
               --ota-footer-link: {{ $style['link_color'] ?? '#1E3A5F' }};
               --ota-footer-link-hover: {{ $style['link_hover_color'] ?? '#0C4A6E' }};
               --ota-footer-accent: {{ $style['accent_color'] ?? '#0284C7' }};">
    <div class="ota-footer-pro__inner ota-footer-desktop-content">
        <div class="ota-footer-pro__grid">
            <div class="ota-footer-pro__brand">
                @if (!empty($brand['logo_url']))
                    <img class="ota-footer-pro__logo" src="{{ $brand['logo_url'] }}" alt="{{ e($brand['name'] ?? '') }}">
                @else
                    <strong class="ota-footer-pro__brand-name">{{ $brand['name'] ?? '' }}</strong>
                @endif
                @if (!empty($brand['description']))
                    <p class="ota-footer-pro__about">{{ $brand['description'] }}</p>
                @endif
                @if (!empty($partnerAgencyName))
                    <p class="ota-footer-pro__partner-agency" data-testid="footer-partner-agency">Partner Agency: {{ $partnerAgencyName }}</p>
                @endif
                @if ($showSupportCard)
                    <div class="ota-footer-pro__support-card">
                        <span class="ota-footer-pro__support-icon" aria-hidden="true"><i class="fa {{ $supportIcon }}"></i></span>
                        <span class="ota-footer-pro__support-text">
                            <strong>{{ $supportCard['title'] ?? '24/7 Support' }}</strong>
                            <small>{{ $supportCard['subtitle'] ?? '' }}</small>
                        </span>
                    </div>
                @endif
                @if ($showBrandEmail || $showBrandWhatsApp)
                    <ul class="ota-footer-pro__brand-contact" aria-label="Footer contact">
                        @if ($showBrandEmail)
                            <li>
                                <i class="fa fa-envelope-o" aria-hidden="true"></i>
                                <a href="mailto:{{ e($brandEmail) }}">{{ $brandEmail }}</a>
                            </li>
                        @endif
                        @if ($showBrandWhatsApp)
                            <li>
                                <i class="fa fa-whatsapp" aria-hidden="true"></i>
                                <a href="https://wa.me/{{ preg_replace('/\D+/', '', $brandWhatsApp) }}" target="_blank" rel="noopener">{{ $contact['whatsapp_label'] ?? 'WhatsApp' }}</a>
                            </li>
                        @endif
                    </ul>
                @endif
            </div>

            <div class="ota-footer-pro__menus">
                @foreach ($f['menu_sections'] ?? [] as $section)
                    @if (! ($section['is_enabled'] ?? true))
                        @continue
                    @endif
                    @php
                        $items = collect($section['items'] ?? [])->filter(fn ($item) => ($item['is_enabled'] ?? true) && !empty($item['label']));
                    @endphp
                    @if ($items->isEmpty())
                        @continue
                    @endif
                    <nav class="ota-footer-pro__col" aria-label="{{ e($section['heading'] ?? '') }}">
                        <span class="ota-footer-pro__heading">{{ $section['heading'] ?? '' }}</span>
                        <ul class="ota-footer-pro__links">
                            @foreach ($items as $item)
                                <li>
                                    <a href="{{ e($item['url'] ?? '/') }}"
                                       @if (!empty($item['open_in_new_tab'])) target="_blank" rel="noopener noreferrer" @endif>
                                        <span>{{ $item['label'] }}</span>
                                        <i class="fa fa-angle-right" aria-hidden="true"></i>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </nav>
                @endforeach
            </div>
        </div>
    </div>

    @php
        $mobileBrandName = trim((string) ($brand['name'] ?? ''));
        if ($mobileBrandName === '') {
            $mobileBrandName = (string) ($brandName ?? config('app.name'));
        }
        $mobileTagline = trim((string) ($brand['description'] ?? ''));
        if ($mobileTagline === '') {
            $mobileTagline = 'Book flights with dedicated support.';
        }
        $mobileContactParts = array_filter([
            ($contact['show_city'] ?? true) ? ($contact['city'] ?? null) : null,
            ($contact['show_phone'] ?? true) ? ($contact['phone'] ?? null) : null,
            ($contact['show_whatsapp'] ?? true) ? ($contact['whatsapp_label'] ?? 'WhatsApp') : null,
        ]);
        $mobileContactLine = $mobileContactParts !== [] ? implode(' · ', $mobileContactParts) : '';
        $mobileCopyright = trim((string) ($bottom['copyright'] ?? ''));
        if ($mobileCopyright === '') {
            $mobileCopyright = '© '.date('Y').' '.$mobileBrandName.'. All rights reserved.';
        }
    @endphp
    <div class="ota-footer-mobile-compact" aria-label="Compact mobile footer">
        <p class="ota-footer-mobile-compact__brand">{{ $mobileBrandName }}</p>
        <p class="ota-footer-mobile-compact__text">{{ $mobileTagline }}</p>
        @if ($mobileContactLine !== '')
            <p class="ota-footer-mobile-compact__contact">{{ $mobileContactLine }}</p>
        @endif
        @if (($bottom['show_trust_badges'] ?? true) && ! empty($bottom['trust_badges']))
            <div class="ota-footer-mobile-compact__badges" aria-label="Trust badges">
                @foreach ($bottom['trust_badges'] as $badge)
                    <span class="ota-footer-mobile-compact__badge">{{ $badge['label'] }}</span>
                @endforeach
            </div>
        @endif
        <p class="ota-footer-mobile-compact__copy">{{ $mobileCopyright }}</p>
    </div>

    <div class="ota-footer-pro__bar ota-footer-desktop-content">
        <div class="ota-footer-pro__bar-inner">
            <div class="ota-footer-pro__bar-main">
                @if (!empty($bottom['copyright']))
                    <span class="ota-footer-pro__copy">{{ $bottom['copyright'] }}</span>
                @endif
                @if (!empty($bottom['powered_by_label']))
                    @if (!empty($bottom['powered_by_url']))
                        <a class="ota-footer-pro__powered" href="{{ e($bottom['powered_by_url']) }}">{{ $bottom['powered_by_label'] }}</a>
                    @else
                        <span class="ota-footer-pro__powered">{{ $bottom['powered_by_label'] }}</span>
                    @endif
                @endif
                @if (($bottom['show_legal_links'] ?? true) && !empty($bottom['legal_links']))
                    <nav class="ota-footer-pro__legal" aria-label="Legal">
                        @foreach ($bottom['legal_links'] as $link)
                            @if ($link['is_enabled'] ?? true)
                                <a href="{{ e($link['url'] ?? '/') }}">{{ $link['label'] }}</a>
                            @endif
                        @endforeach
                    </nav>
                @endif
            </div>
            <div class="ota-footer-pro__bar-meta">
                @if (!empty($bottom['disclaimer']))
                    <span class="ota-footer-pro__disclaimer">{{ $bottom['disclaimer'] }}</span>
                @endif
                @if (($bottom['show_trust_badges'] ?? true) && ! empty($bottom['trust_badges']))
                    <div class="ota-footer-pro__badges" aria-label="Trust badges">
                        @foreach ($bottom['trust_badges'] as $badge)
                            <span class="ota-footer-pro__badge">{{ $badge['label'] }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</footer>
