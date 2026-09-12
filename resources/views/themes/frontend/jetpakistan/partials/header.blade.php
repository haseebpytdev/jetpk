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
            <x-jp.icon name="chevron-down" class="jp-register-menu__chev" />
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
            <x-jp.icon name="moon" class="ico-moon" />
            <x-jp.icon name="sun" class="ico-sun" />
          </span>
        </button>
      @endif
      <button class="hamburger jp-header-mobile-trigger" id="openDrawer" aria-label="Open menu"><x-jp.icon name="menu" /></button>
    </div>
  </div>
</header>
