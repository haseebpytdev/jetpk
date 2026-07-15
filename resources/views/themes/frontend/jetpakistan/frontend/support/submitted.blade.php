@extends('themes.frontend.jetpakistan.layouts.frontend')

@php
    $brandName = client_branding()->companyName();
@endphp

@section('title', 'Support request received - '.$brandName)

@section('content')
<section class="jp-page jp-page--support-submitted">
  <div class="wrap jp-page-wrap">
    <x-jp.page-hero
      kicker="Support"
      title="Request received"
      :description="'Thank you. Your support reference is '.$ticketReference.'. Our team will respond using the contact details you provided.'"
    />
    <x-jp.card>
      <p>Keep this reference handy if you follow up by phone or email.</p>
      <div class="jp-page-actions">
        <a href="{{ client_route('support') }}" class="jp-btn jp-btn--secondary">Back to support</a>
        <a href="{{ client_route('home') }}" class="jp-btn jp-btn--primary">Back to home</a>
      </div>
    </x-jp.card>
  </div>
</section>
@endsection
