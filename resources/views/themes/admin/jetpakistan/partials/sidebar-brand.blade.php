@php
    $brandName = uses_jetpk_company_branding()
        ? jetpk_company_branding()->companyName()
        : ($dashProductName ?? 'JetPakistan');
    $logoUrl = uses_jetpk_company_branding()
        ? jetpk_company_branding()->logoUrl()
        : null;
@endphp
<a href="{{ $dashHomeUrl ?? client_route('admin.dashboard') }}" class="jp-side2__brand">
    @if ($logoUrl)
        <img src="{{ $logoUrl }}" alt="{{ $brandName }}" class="jp-side2__brand-logo">
    @else
        <span class="jp-logo__mark" aria-hidden="true">JP</span>
    @endif
    <span>{{ $brandName }}</span>
</a>
