@php
    use App\Services\Client\ClientGlobalContactResolver;
    $jpAuthCardClass = trim($__env->yieldContent('auth_card_class'));
    $jpAuthBrand = client_branding()->companyName();
    $jpContact = app(ClientGlobalContactResolver::class)->contact();
    $jpSupportEmail = $jpContact['email'] !== '' ? $jpContact['email'] : client_branding()->email();
    $jpSupportPhone = $jpContact['phone'] !== '' ? $jpContact['phone'] : client_branding()->phone();
    $jpAuthLogoUrl = client_branding()->logoUrl();
    $sidePanel = is_array($content['side_panel'] ?? null) ? $content['side_panel'] : [];
@endphp

@extends('themes.frontend.jetpakistan.layouts.frontend')

@section('jp_body_class', 'jp-auth-body')

@section('content')
  <section class="jp-auth-page" aria-label="Secure account access">
    <div class="wrap jp-auth-wrap">
      <aside class="jp-auth-story">
        @if (($sidePanel['eyebrow'] ?? '') !== '')
          <span class="eyebrow">{{ $sidePanel['eyebrow'] }}</span>
        @endif
        <h1>{{ $sidePanel['title'] ?? ('Book, manage, and track travel with '.$jpAuthBrand.'.') }}</h1>
        @if (($sidePanel['body'] ?? '') !== '')
          <p>{{ $sidePanel['body'] }}</p>
        @endif
        <div class="jp-auth-contact">
          @if ($jpSupportPhone !== '')
            <span><x-jp.icon name="phone" />{{ $jpSupportPhone }}</span>
          @endif
          @if ($jpSupportEmail !== '')
            <span><x-jp.icon name="chat" />{{ $jpSupportEmail }}</span>
          @endif
        </div>
      </aside>

      <section class="jp-auth-card {{ $jpAuthCardClass }}" data-auth-premium-layout>
        <a href="{{ client_route('home') }}" class="jp-auth-brand" aria-label="{{ e($jpAuthBrand) }} home">
          @if ($jpAuthLogoUrl)
            <img src="{{ $jpAuthLogoUrl }}" alt="{{ $jpAuthBrand }}" class="jp-auth-brand__logo">
          @else
            <span class="mark"><x-jp.icon name="plane" /></span>
            <span>{{ $jpAuthBrand }}<small>Secure account access</small></span>
          @endif
        </a>

        @stack('auth_form')

        <p class="jp-auth-help">
          @if (($content['footer_text'] ?? '') !== '')
            {{ $content['footer_text'] }}
          @else
            Need help? <a href="{{ client_route('support') }}">Contact {{ $jpAuthBrand }} support</a>.
          @endif
        </p>
      </section>
    </div>
  </section>
@endsection
