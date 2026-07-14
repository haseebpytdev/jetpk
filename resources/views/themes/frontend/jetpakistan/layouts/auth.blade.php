@php
    $jpAuthCardClass = trim($__env->yieldContent('auth_card_class'));
    $jpAuthBrand = client_branding()->companyName();
    $jpSupportEmail = client_branding()->email() ?: 'ticketingjp@jetpakistan.com';
    $jpSupportPhone = client_branding()->phone() ?: '0311 1222427';
    $jpAuthLogoUrl = client_branding()->logoUrl();
@endphp

@extends('themes.frontend.jetpakistan.layouts.frontend')

@section('jp_body_class', 'jp-auth-body')

@section('content')
  <section class="jp-auth-page" aria-label="Secure account access">
    <div class="wrap jp-auth-wrap">
      <aside class="jp-auth-story">
        <span class="eyebrow">Secure portal</span>
        <h1>Book, manage, and track travel with {{ $jpAuthBrand }}.</h1>
        <p>Use the same trusted OTA account flow with {{ $jpAuthBrand }} branding, PKR fares, booking updates, and human support when your plans change.</p>
        <div class="jp-auth-contact">
          <span><x-jp.icon name="phone" />{{ $jpSupportPhone }}</span>
          <span><x-jp.icon name="chat" />{{ $jpSupportEmail }}</span>
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
          Need help? <a href="{{ client_route('support') }}">Contact {{ $jpAuthBrand }} support</a>.
        </p>
      </section>
    </div>
  </section>
@endsection
