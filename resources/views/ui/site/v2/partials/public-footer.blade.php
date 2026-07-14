@php
    $f = $footerPresentation ?? [];
    if (! ($f['is_enabled'] ?? true)) {
        return;
    }
    $brand = $f['brand'] ?? [];
    $supportCard = $f['support_card'] ?? [];
    $contact = $f['contact'] ?? [];
    $bottom = $f['bottom_bar'] ?? [];
    $brandEmail = trim((string) ($contact['email'] ?? ''));
    $brandWhatsApp = trim((string) ($contact['whatsapp'] ?? ''));
    $showBrandEmail = ($contact['is_enabled'] ?? true) && ($contact['show_email'] ?? true) && $brandEmail !== '';
    $showBrandWhatsApp = ($contact['is_enabled'] ?? true) && ($contact['show_whatsapp'] ?? true) && $brandWhatsApp !== '';
    $displayBrandName = trim((string) ($brand['name'] ?? ''));
    if ($displayBrandName === '') {
        $displayBrandName = (string) ($brandName ?? config('app.name'));
    }
    $copyright = trim((string) ($bottom['copyright'] ?? ''));
    if ($copyright === '') {
        $copyright = '© '.date('Y').' '.$displayBrandName.'. All rights reserved.';
    }

    $preserveFooterHref = static function (?string $href): string {
        $href = trim((string) $href);
        if ($href === '') {
            return ui_preserve_url('/');
        }
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            $parsed = parse_url($href);
            $path = $parsed['path'] ?? '/';
            $query = isset($parsed['query']) && $parsed['query'] !== '' ? '?'.$parsed['query'] : '';

            return url(ui_preserve_url($path.$query));
        }

        return ui_preserve_url($href);
    };

    $essentialLinks = collect($f['menu_sections'] ?? [])
        ->filter(fn ($section) => ($section['is_enabled'] ?? true))
        ->flatMap(fn ($section) => collect($section['items'] ?? [])
            ->filter(fn ($item) => ($item['is_enabled'] ?? true) && ! empty($item['label']))
            ->map(fn ($item) => [
                'label' => $item['label'],
                'url' => $item['url'] ?? '/',
                'open_in_new_tab' => $item['open_in_new_tab'] ?? false,
            ]))
        ->unique('label')
        ->take(6)
        ->values();
@endphp
<footer class="ota-v2-public-footer ota-v2-public-footer--utility" aria-label="Site footer" data-testid="v2-public-footer">
    <div class="ota-v2-page-wrap ota-v2-public-footer__utility-row">
        <div class="ota-v2-public-footer__brand-compact">
            @if (! empty($brand['logo_url']))
                <img class="ota-v2-public-footer__logo" src="{{ $brand['logo_url'] }}" alt="{{ e($displayBrandName) }}">
            @else
                <strong class="ota-v2-public-footer__brand-name">{{ $displayBrandName }}</strong>
            @endif
            @if (($supportCard['is_enabled'] ?? true))
                <span class="ota-v2-public-footer__trust-line">
                    <i class="fa fa-headphones" aria-hidden="true"></i>
                    {{ $supportCard['title'] ?? '24/7 Support' }}
                </span>
            @endif
        </div>

        @if ($essentialLinks->isNotEmpty())
            <nav class="ota-v2-public-footer__essential" aria-label="Footer links">
                @foreach ($essentialLinks as $item)
                    <a href="{{ $preserveFooterHref($item['url']) }}"
                       @if (! empty($item['open_in_new_tab'])) target="_blank" rel="noopener noreferrer" @endif>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
        @endif

        @if ($showBrandEmail || $showBrandWhatsApp)
            <div class="ota-v2-public-footer__contact-compact" aria-label="Footer contact">
                @if ($showBrandEmail)
                    <a href="mailto:{{ e($brandEmail) }}">{{ $brandEmail }}</a>
                @endif
                @if ($showBrandWhatsApp)
                    <a href="https://wa.me/{{ preg_replace('/\D+/', '', $brandWhatsApp) }}" target="_blank" rel="noopener">
                        {{ $contact['whatsapp_label'] ?? 'WhatsApp' }}
                    </a>
                @endif
            </div>
        @endif
    </div>

    <div class="ota-v2-public-footer__meta">
        <div class="ota-v2-page-wrap ota-v2-public-footer__meta-inner">
            <span class="ota-v2-public-footer__copy">{{ $copyright }}</span>
            @if (! empty($bottom['powered_by_label']))
                @if (! empty($bottom['powered_by_url']))
                    <a class="ota-v2-public-footer__meta-link" href="{{ e($bottom['powered_by_url']) }}">{{ $bottom['powered_by_label'] }}</a>
                @else
                    <span class="ota-v2-public-footer__meta-link">{{ $bottom['powered_by_label'] }}</span>
                @endif
            @endif
            @if (($bottom['show_legal_links'] ?? true) && ! empty($bottom['legal_links']))
                <nav class="ota-v2-public-footer__legal" aria-label="Legal">
                    @foreach ($bottom['legal_links'] as $link)
                        @if ($link['is_enabled'] ?? true)
                            <a href="{{ $preserveFooterHref($link['url'] ?? '/') }}">{{ $link['label'] }}</a>
                        @endif
                    @endforeach
                </nav>
            @endif
            <div class="ota-v2-public-footer__badges" aria-label="Security">
                @if (($bottom['show_trust_badges'] ?? true) && ! empty($bottom['trust_badges']))
                    @foreach ($bottom['trust_badges'] as $badge)
                        <span class="ota-v2-public-footer__badge">{{ $badge['label'] }}</span>
                    @endforeach
                @else
                    <span class="ota-v2-public-footer__badge"><i class="fa fa-lock" aria-hidden="true"></i> SSL Secure</span>
                @endif
            </div>
        </div>
    </div>
</footer>
