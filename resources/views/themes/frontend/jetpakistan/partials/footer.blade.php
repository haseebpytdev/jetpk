@php
    $brandName = client_branding()->companyName();
    $jpFooterText = trim(client_branding()->footerText());
@endphp
<footer class="footer">
  <div class="wrap">
    <div class="foot-top">
      <div class="foot-brand">
        <x-jp.brand-logo />
        <p>{{ $jpFooterText !== '' ? $jpFooterText : "Pakistan's premium flight booking platform. Honest fares, instant tickets, human support." }}</p>
        <div class="foot-badges">
          <span class="fbadge"><x-jp.icon name="shield" />IATA</span>
          <span class="fbadge"><x-jp.icon name="check" />PCAA</span>
          <span class="fbadge"><x-jp.icon name="lock" />PCI-DSS</span>
        </div>
      </div>
      <div class="fcol"><h4>Company</h4><a href="{{ client_route('about') }}">About us</a><a href="{{ client_route('support') }}">Contact</a></div>
      <div class="fcol"><h4>Policies</h4><a href="{{ client_route('pages.show', ['slug' => 'terms-and-conditions']) }}">Terms</a><a href="{{ client_route('pages.show', ['slug' => 'privacy-policy']) }}">Privacy</a></div>
      <div class="fcol"><h4>Support</h4><a href="{{ client_route('support') }}">Help centre</a><a href="{{ client_route('booking.lookup') }}">Manage booking</a><a href="{{ client_route('support') }}">Contact</a></div>
      <div class="fcol"><h4>B2B & agents</h4><a href="{{ client_route('agent.register') }}">Become an agent</a><a href="{{ client_route('agent.register.form') }}">Apply now</a><a href="{{ client_route('support') }}">Partner support</a></div>
    </div>
    <div class="foot-bot">
      <p>&copy; {{ date('Y') }} {{ $brandName }}. All rights reserved.</p>
      <div class="socials">
        <a href="{{ client_route('support') }}" aria-label="Contact support"><x-jp.icon name="chat" /></a>
        <a href="https://www.facebook.com/jetpakistancom/" target="_blank" rel="noopener" aria-label="Facebook"><x-jp.icon name="x" /></a>
        <a href="https://www.instagram.com/jetpakistanofficial" target="_blank" rel="noopener" aria-label="Instagram"><x-jp.icon name="instagram" /></a>
      </div>
    </div>
  </div>
</footer>
