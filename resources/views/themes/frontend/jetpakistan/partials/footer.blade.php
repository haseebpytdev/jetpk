@php
    use App\Services\Client\ClientHeaderFooterPresenter;
    use App\Services\Client\ClientPageRenderer;
    $jpFooter = app(ClientHeaderFooterPresenter::class)->footer();
    $renderer = app(ClientPageRenderer::class);
    $brandName = client_branding()->companyName();
    $intro = $jpFooter['intro'] !== '' ? $jpFooter['intro'] : trim(client_branding()->footerText());
    $legal = $jpFooter['legal'] ?? [];
    $social = $jpFooter['social'] ?? [];
@endphp
<footer class="footer">
  <div class="wrap">
    <div class="foot-top">
      <div class="foot-brand">
        <x-jp.brand-logo />
        @if ($intro !== '')
          <p>{{ $intro }}</p>
        @endif
        <div class="foot-badges">
          <span class="fbadge"><x-jp.icon name="shield" />IATA</span>
          <span class="fbadge"><x-jp.icon name="check" />PCAA</span>
          <span class="fbadge"><x-jp.icon name="lock" />PCI-DSS</span>
        </div>
      </div>
      @foreach ($jpFooter['columns'] as $column)
        <div class="fcol">
          <h4>{{ $column['title'] ?? '' }}</h4>
          @foreach ($renderer->enabledItems($column['links'] ?? []) as $link)
            <a href="{{ $renderer->resolveDestination((string) ($link['url'] ?? $link['destination'] ?? '')) }}">{{ $link['label'] ?? '' }}</a>
          @endforeach
        </div>
      @endforeach
      @if ($jpFooter['columns'] === [])
        <div class="fcol"><h4>Company</h4><a href="{{ client_route('about') }}">About us</a><a href="{{ client_route('support') }}">Contact</a></div>
        <div class="fcol"><h4>Policies</h4><a href="{{ client_route('terms') }}">Terms</a><a href="{{ client_route('privacy') }}">Privacy</a></div>
        <div class="fcol"><h4>Support</h4><a href="{{ client_route('faq') }}">Help centre</a><a href="{{ client_route('booking.lookup') }}">Manage booking</a></div>
        <div class="fcol"><h4>B2B & agents</h4><a href="{{ client_route('agent.register') }}">Become an agent</a></div>
      @endif
    </div>
    <div class="foot-bot">
      <p>{{ ($legal['copyright'] ?? '') !== '' ? str_replace('{year}', date('Y'), $legal['copyright']) : ('© '.date('Y').' '.$brandName.'. All rights reserved.') }}</p>
      <div class="socials">
        <a href="{{ client_route('support') }}" aria-label="Contact support"><x-jp.icon name="chat" /></a>
        @foreach ($social as $item)
          @if (($item['url'] ?? '') !== '')
            <a href="{{ $item['url'] }}" target="_blank" rel="noopener" aria-label="{{ $item['platform'] ?? 'Social' }}"><x-jp.icon name="x" /></a>
          @endif
        @endforeach
      </div>
    </div>
  </div>
</footer>
