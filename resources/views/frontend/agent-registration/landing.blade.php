@extends(client_layout('frontend', 'frontend'))

@section('title', 'Agent signup')

@section('content')
    <section class="ota-section ota-routes-section ota-agent-landing-rhythm">
        <div class="ota-container ota-container-form">
            <header class="ota-section-head">
                <p class="ota-section-kicker">Agent partnership</p>
                <h1 class="ota-section-title">Join the {{ $brandName }} Agent Network</h1>
                <p class="ota-section-desc">Partner with our OTA platform to manage client bookings, track performance, and grow your agency sales.</p>
            </header>
            <div class="ota-hero-actions" style="justify-content:center;margin-top:10px;">
                <a href="{{ client_route('agent.register.form') }}" class="ota-btn ota-btn-primary">Apply as Agent</a>
            </div>
            <div class="row" style="margin-top:18px;">
                <div class="col-md-12">
                    <div class="ota-checkout-card">
                        <h3 class="ota-checkout-section-title">How it works</h3>
                        <div class="row">
                            <div class="col-sm-3"><div class="ota-checkout-card ota-checkout-card--muted"><strong>1. Submit application</strong><p class="ota-checkout-section-hint">Share your agency and verification details.</p></div></div>
                            <div class="col-sm-3"><div class="ota-checkout-card ota-checkout-card--muted"><strong>2. Admin review</strong><p class="ota-checkout-section-hint">Our team validates your business profile.</p></div></div>
                            <div class="col-sm-3"><div class="ota-checkout-card ota-checkout-card--muted"><strong>3. Receive activation link</strong><p class="ota-checkout-section-hint">Approved partners receive onboarding instructions.</p></div></div>
                            <div class="col-sm-3"><div class="ota-checkout-card ota-checkout-card--muted"><strong>4. Start booking</strong><p class="ota-checkout-section-hint">Access partner tools and submit booking requests.</p></div></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ota-checkout-card">
                        <h3 class="ota-checkout-section-title">Benefits</h3>
                        <div class="row">
                            <div class="col-xs-6"><div class="ota-checkout-card ota-checkout-card--muted"><strong>Agent dashboard</strong><p class="ota-checkout-section-hint">One place to manage requests.</p></div></div>
                            <div class="col-xs-6"><div class="ota-checkout-card ota-checkout-card--muted"><strong>Booking tools</strong><p class="ota-checkout-section-hint">Create and track bookings quickly.</p></div></div>
                            <div class="col-xs-6"><div class="ota-checkout-card ota-checkout-card--muted"><strong>Commission tracking</strong><p class="ota-checkout-section-hint">Monitor earnings by trip.</p></div></div>
                            <div class="col-xs-6"><div class="ota-checkout-card ota-checkout-card--muted"><strong>Priority support</strong><p class="ota-checkout-section-hint">Faster help for partner issues.</p></div></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ota-checkout-card">
                        <h3 class="ota-checkout-section-title">FAQ</h3>
                        <div class="ota-checkout-card ota-checkout-card--muted">
                            <p class="ota-checkout-section-hint"><strong>Who can apply?</strong><br>Licensed agencies, consultants, and travel businesses handling customer bookings.</p>
                        </div>
                        <div class="ota-checkout-card ota-checkout-card--muted">
                            <p class="ota-checkout-section-hint"><strong>Is approval instant?</strong><br>No, every application is reviewed before access is granted.</p>
                        </div>
                        <div class="ota-checkout-card ota-checkout-card--muted">
                            <p class="ota-checkout-section-hint"><strong>Are there registration fees?</strong><br>No registration fee is charged for application submission.</p>
                        </div>
                        <div class="ota-checkout-card ota-checkout-card--muted">
                            <p class="ota-checkout-section-hint"><strong>When do I get dashboard access?</strong><br>Only after approval and account activation email setup.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
