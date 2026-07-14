@php
    $brandName = client_branding()->companyName();
@endphp
<a href="{{ client_route($portalVariant === 'agent' ? 'agent.dashboard' : 'customer.dashboard') }}" class="jp-portal__logo" aria-label="{{ $brandName }} portal">
  @if (client_branding()->logoUrl())
    <img src="{{ client_branding()->logoUrl() }}" alt="{{ $brandName }}" class="jp-portal__logo-img">
  @else
    <span class="mark"><x-jp.icon name="plane" /></span>
    <span class="logo__wordmark">{{ $brandName }}</span>
  @endif
</a>
