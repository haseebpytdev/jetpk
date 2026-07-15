@extends('themes.frontend.jetpakistan.layouts.frontend')

@section('title', 'Agent partnership — JetPakistan')

@section('content')
<section class="jp-page jp-page--agent-landing" aria-labelledby="jp-agent-heading">
  <div class="wrap jp-page-wrap">
    <x-jp.page-hero
      id="jp-agent-heading"
      kicker="Agent partnership"
      title="Join the JetPakistan agent network"
      description="Partner with our OTA platform to manage client bookings, track performance, and grow your agency sales with PKR fares and dedicated support."
    />

    <div class="jp-page-actions">
      <a href="{{ client_route('agent.register.form') }}" class="jp-btn jp-btn--primary">Apply as agent</a>
      <a href="{{ client_route('support') }}" class="jp-btn jp-btn--secondary">Partner support</a>
    </div>

    <div class="jp-page-grid jp-page-grid--3 jp-agent-steps">
      <x-jp.card title="1. Submit application">
        <p>Share your agency and verification details through our secure application form.</p>
      </x-jp.card>
      <x-jp.card title="2. Admin review">
        <p>Our team validates your business profile and may request supporting documents.</p>
      </x-jp.card>
      <x-jp.card title="3. Start booking">
        <p>Approved partners receive onboarding instructions and agent dashboard access.</p>
      </x-jp.card>
    </div>

    <div class="jp-page-grid jp-page-grid--2">
      <x-jp.card title="Benefits for agents">
        <ul class="jp-list">
          <li>Agent dashboard to manage booking requests in one place</li>
          <li>Fast flight search and fare tools for client itineraries</li>
          <li>Commission tracking and performance visibility</li>
          <li>Priority partner support for urgent travel issues</li>
        </ul>
      </x-jp.card>

      <x-jp.card title="FAQ">
        <details class="jp-faq">
          <summary>Who can apply?</summary>
          <p>Licensed agencies, consultants, and travel businesses handling customer bookings.</p>
        </details>
        <details class="jp-faq">
          <summary>Is approval instant?</summary>
          <p>No — every application is reviewed before access is granted.</p>
        </details>
        <details class="jp-faq">
          <summary>Are there registration fees?</summary>
          <p>No registration fee is charged for application submission.</p>
        </details>
        <details class="jp-faq">
          <summary>When do I get dashboard access?</summary>
          <p>Only after approval and account activation email setup.</p>
        </details>
      </x-jp.card>
    </div>

    <x-jp.card title="Ready to partner?">
      <p class="jp-card__lead">Complete the agency application and our team will contact you after verification.</p>
      <div class="jp-page-actions">
        <a href="{{ client_route('agent.register.form') }}" class="jp-btn jp-btn--primary">Start application</a>
        <a href="{{ client_route('login') }}" class="jp-btn jp-btn--secondary">Agent log in</a>
      </div>
    </x-jp.card>
  </div>
</section>
@endsection
