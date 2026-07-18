@php
    use App\Services\Client\ClientHeaderFooterPresenter;
    $jpHeader = app(ClientHeaderFooterPresenter::class)->header();
    $navItems = $jpHeader['nav_items'] ?? [];
@endphp
<header class="header jp-site-header" id="header">
  @php $announcement = $jpHeader['announcement'] ?? []; @endphp
  @if (($announcement['enabled'] ?? '0') === '1' && ($announcement['text'] ?? '') !== '')
    <div class="jp-announcement jp-announcement--{{ $announcement['style'] ?? 'info' }}">
      <div class="wrap">
        @if (($announcement['link'] ?? '') !== '')
          <a href="{{ app(\App\Services\Client\ClientPageRenderer::class)->resolveDestination((string) $announcement['link']) }}">{{ $announcement['text'] }}</a>
        @else
          <span>{{ $announcement['text'] }}</span>
        @endif
      </div>
    </div>
  @endif
  <div class="wrap jp-header-container">
    <x-jp.brand-logo />
    <nav class="nav jp-header-nav" aria-label="Primary">
      @foreach ($navItems as $item)
        @php
          $destination = app(\App\Services\Client\ClientPageRenderer::class)->resolveDestination((string) ($item['destination'] ?? ''));
          $routeName = str_replace('route:', '', (string) ($item['destination'] ?? ''));
        @endphp
        <a href="{{ $destination }}" @class(['active' => $routeName !== '' && request()->routeIs($routeName, 'client.parity.'.$routeName.'*')])>{{ $item['label'] ?? '' }}</a>
      @endforeach
    </nav>
    <div class="header-right jp-header-actions">
      @if (($jpHeader['support_pill_label'] ?? '') !== '')
        <a href="{{ $jpHeader['support_pill_url'] ?? client_route('support') }}" class="support-pill jp-header-support" aria-label="{{ $jpHeader['support_pill_label'] }}">
          <x-jp.icon name="phone" />
          <span class="mono">{{ $jpHeader['support_pill_label'] }}</span>
        </a>
      @endif
      @guest
        <a href="{{ client_route('login') }}" class="signin jp-header-signin">{{ $jpHeader['sign_in_label'] ?? 'Sign in' }}</a>
        <div class="jp-register-menu" data-jp-register-menu>
          <button type="button" class="btn btn-primary jp-register-menu__trigger jp-header-register" aria-expanded="false" aria-haspopup="true">
            {{ $jpHeader['register_label'] ?? 'Register' }}
            <svg class="jp-register-menu__chev" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6" fill="none" stroke="currentColor" stroke-width="2.2"/></svg>
          </button>
          <div class="jp-register-menu__panel" hidden>
            <a href="{{ client_route('register') }}">Customer Registration</a>
            <a href="{{ client_route('agent.register') }}">Agent Registration</a>
          </div>
        </div>
      @else
        <x-account-dropdown variant="desktop" />
      @endguest
      @if ($jpHeader['theme_toggle_visible'] ?? true)
        <button class="toggle jp-header-theme-toggle" id="themeToggle" aria-label="Switch day or night theme">
          <span class="knob">
            <svg class="ico-moon" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" stroke="none" fill="currentColor"/></svg>
            <svg class="ico-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4" stroke="none" fill="currentColor"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" stroke="currentColor" stroke-width="2.4"/></svg>
          </span>
        </button>
      @endif
      <button class="hamburger jp-header-mobile-trigger" id="openDrawer" aria-label="Open menu"><x-jp.icon name="menu" /></button>
    </div>
  </div>
</header>
