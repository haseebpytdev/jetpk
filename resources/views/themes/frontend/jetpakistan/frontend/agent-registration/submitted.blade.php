@extends('themes.frontend.jetpakistan.layouts.frontend')

@section('title', 'Application submitted — JetPakistan')

@section('content')
<section class="jp-page jp-page--agent-submitted" aria-labelledby="jp-agent-submitted-heading">
  <div class="wrap jp-page-wrap jp-page-wrap--narrow">
    <x-jp.page-hero
      id="jp-agent-submitted-heading"
      kicker="Application received"
      title="Thank you — your application was submitted"
      description="Our team will review your details and contact you after verification."
    />

    @if (session('status'))
      <x-jp.alert variant="warning">{{ session('status') }}</x-jp.alert>
    @endif

    <x-jp.alert variant="warning">
      You will receive login access only after approval.
    </x-jp.alert>

    <x-jp.card title="What happens next?">
      <ul class="jp-list">
        <li>Our partnerships team reviews your agency profile</li>
        <li>We may contact you for verification documents</li>
        <li>Approved agents receive onboarding and dashboard access by email</li>
      </ul>
    </x-jp.card>

    <div class="jp-page-actions">
      <a href="{{ client_route('support') }}" class="jp-btn jp-btn--primary">Contact support</a>
      <a href="{{ client_route('agent.register') }}" class="jp-btn jp-btn--secondary">Agent info</a>
      <a href="{{ client_route('home') }}" class="jp-btn jp-btn--secondary">Back to home</a>
    </div>
  </div>
</section>
@endsection
