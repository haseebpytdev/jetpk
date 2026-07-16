@extends('themes.frontend.jetpakistan.layouts.frontend')

@section('title', 'About us - JetPakistan')

@section('content')
@php
    use App\Support\Client\ClientPageKeys;

    $aboutKicker = client_page_content(ClientPageKeys::ABOUT, 'hero.kicker', 'About JetPakistan');
    $aboutTitle = client_page_content(ClientPageKeys::ABOUT, 'hero.title', 'Cheap flights and secure online booking for Pakistan');
    $aboutDescription = client_page_content(ClientPageKeys::ABOUT, 'hero.description', 'JetPakistan helps travellers discover low fares, compare airlines, and complete domestic and international flight bookings online with confidence.');
    $aboutPhone = client_page_content(ClientPageKeys::ABOUT, 'contact.phone', '0311 1222427');
    $aboutEmail = client_page_content(ClientPageKeys::ABOUT, 'contact.email', 'ota@jetpakistan.pk');
    $aboutWebsite = client_page_content(ClientPageKeys::ABOUT, 'contact.website', 'https://www.jetpakistan.com');
    $aboutOffice = client_page_content(ClientPageKeys::ABOUT, 'contact.office', 'Office No. 220, 2nd Floor, Century Tower, Kalma Chowk, Gulberg III, Lahore');
@endphp
<section class="jp-page jp-page--about" aria-labelledby="jp-about-heading">
  <div class="wrap jp-page-wrap">
    <x-jp.page-hero
      id="jp-about-heading"
      :kicker="$aboutKicker"
      :title="$aboutTitle"
      :description="$aboutDescription"
    />

    <div class="jp-feature-strip jp-page-grid jp-page-grid--3">
      <x-jp.card title="Lowest fare discovery">
        <p>Search hundreds of routes from Pakistan and compare airline options side by side before you book.</p>
      </x-jp.card>
      <x-jp.card title="Secure online booking">
        <p>Book with clear pricing in PKR, protected checkout, and support if your plans change.</p>
      </x-jp.card>
      <x-jp.card title="Mobile travel app">
        <p>Search, book, and manage trips on the go with the JetPakistan mobile experience.</p>
      </x-jp.card>
    </div>

    <div class="jp-page-grid jp-page-grid--2">
      <x-jp.card title="Why JetPakistan">
        <ul class="jp-list">
          <li>Cheap air tickets for domestic and international travel from Pakistan</li>
          <li>Transparent fares with no surprise charges at checkout</li>
          <li>Online check-in guidance and e-ticket delivery</li>
          <li>Human support for booking changes, invoices, and travel questions</li>
          <li>Popular routes: Lahore, Karachi, Islamabad, Dubai, Jeddah, and beyond</li>
        </ul>
      </x-jp.card>

      <x-jp.card title="Domestic & international travel">
        <p>Whether you are flying within Pakistan or heading abroad for work, Umrah, or leisure, JetPakistan brings airline options together in one place.</p>
        <p>Compare departure times, cabin classes, and total price — then book the itinerary that fits your schedule and budget.</p>
      </x-jp.card>

      <x-jp.card title="Booking confidence">
        <p>Every search is designed to be simple: pick your route, choose dates, select travellers, and confirm. Our team is available when you need help before or after purchase.</p>
      </x-jp.card>

      <x-jp.card title="Contact JetPakistan">
        <ul class="jp-list jp-list--contact">
          @if ($aboutPhone !== '')
            <li><strong>Phone:</strong> <a href="tel:+923111222427">{{ $aboutPhone }}</a></li>
          @endif
          @if ($aboutEmail !== '')
            <li><strong>Email:</strong> <a href="mailto:{{ $aboutEmail }}">{{ $aboutEmail }}</a></li>
          @endif
          @if ($aboutWebsite !== '')
            <li><strong>Website:</strong> <a href="{{ $aboutWebsite }}" target="_blank" rel="noopener">{{ parse_url($aboutWebsite, PHP_URL_HOST) ?: $aboutWebsite }}</a></li>
          @endif
          @if ($aboutOffice !== '')
            <li><strong>Office:</strong> {{ $aboutOffice }}</li>
          @endif
        </ul>
      </x-jp.card>
    </div>

    <div class="jp-page-actions">
      <a href="{{ client_route('support') }}" class="jp-btn jp-btn--secondary">Contact support</a>
      <a href="{{ client_route('home') }}#jp-flight-search" class="jp-btn jp-btn--primary">Search flights</a>
    </div>
  </div>
</section>
@endsection
