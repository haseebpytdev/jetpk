@php
    use App\Services\Client\ClientHeaderFooterPresenter;
    $jpHeader = app(ClientHeaderFooterPresenter::class)->header();
    $navItems = $jpHeader['nav_items'] ?? [];
@endphp
<div class="drawer" id="drawer">
  <div class="scrim" data-close></div>
  <div class="panel">
    <div class="d-head">
      <x-jp.brand-logo />
      <button class="hamburger" data-close aria-label="Close menu" style="display:grid"><x-jp.icon name="close" /></button>
    </div>
    @foreach ($navItems as $item)
      <a href="{{ app(\App\Services\Client\ClientPageRenderer::class)->resolveDestination((string) ($item['destination'] ?? '')) }}">{{ $item['label'] ?? '' }}</a>
    @endforeach
    @auth
      <div class="d-account">
        <x-account-dropdown variant="mobile" />
      </div>
    @endauth
    <div class="d-actions">
      @guest
        <a href="{{ client_route('login') }}" class="btn btn-ghost btn-lg">{{ $jpHeader['sign_in_label'] ?? 'Sign in' }}</a>
        <div class="d-register">
          <span class="d-register-label">{{ $jpHeader['register_label'] ?? 'Register' }}</span>
          <a href="{{ client_route('register') }}" class="btn btn-primary btn-lg">Customer Registration</a>
          <a href="{{ client_route('agent.register') }}" class="btn btn-ghost btn-lg">Agent Registration</a>
        </div>
      @else
        <form method="POST" action="{{ route('logout') }}" class="d-logout-form">
          @csrf
          <button type="submit" class="btn btn-ghost btn-lg">Log out</button>
        </form>
      @endguest
    </div>
  </div>
</div>
