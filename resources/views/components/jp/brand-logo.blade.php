@props([
    'href' => null,
    'variant' => 'public',
    'class' => '',
])

@php
    $brandName = uses_jetpk_company_branding()
        ? jetpk_company_branding()->companyName()
        : (is_client_preview() ? client_branding()->companyName() : 'JetPakistan');
    $logoUrl = uses_jetpk_company_branding()
        ? jetpk_company_branding()->logoUrl()
        : (is_client_preview() ? client_branding()->logoUrl() : null);
    $link = $href ?? (function_exists('client_route') ? client_route('home') : '/');
    $isLink = $href !== false;
@endphp

@if ($isLink)
    <a href="{{ $link }}" {{ $attributes->merge(['class' => 'logo '.$class]) }} aria-label="{{ $brandName }}">
@else
    <div {{ $attributes->merge(['class' => 'logo '.$class]) }} aria-label="{{ $brandName }}">
@endif
    @if ($logoUrl)
        <img src="{{ $logoUrl }}" alt="{{ $brandName }}" class="logo__img logo__img--{{ $variant }}" loading="eager">
    @else
        <span class="mark" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3.5c-.5-.5-2.5 0-4 1.5L13.5 8.5 5.3 6.7c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 3.8c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z" stroke="none" fill="currentColor"/></svg></span>
        <span class="logo__wordmark">Jet<b>Pakistan</b></span>
    @endif
@if ($isLink)
    </a>
@else
    </div>
@endif
