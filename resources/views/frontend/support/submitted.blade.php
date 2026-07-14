@extends(client_layout('frontend', 'frontend'))

@section('title', 'Support request received - '.$brandName)

@section('content')
    <section class="ota-section ota-form-page ota-support-submitted-page" aria-labelledby="ota-support-submitted-heading">
        <div class="ota-container">
            <div class="ota-support-submitted-card" data-testid="support-submitted-card">
                <div class="ota-support-submitted-icon" aria-hidden="true">
                    <span class="ota-support-submitted-check"></span>
                </div>
                <h1 id="ota-support-submitted-heading" class="ota-support-submitted-title">Request received</h1>
                <p class="ota-support-submitted-lead">
                    Thank you. Our support team has your request and will respond as soon as possible, usually within 2–6 business hours.
                </p>
                <div class="ota-support-submitted-ref">
                    <span class="ota-support-submitted-ref-label">Your reference</span>
                    <strong class="ota-support-submitted-ref-value" data-testid="support-ticket-reference">{{ $ticketReference }}</strong>
                </div>
                <p class="ota-support-submitted-note">Please keep this reference for follow-up. We may contact you by email with updates.</p>
                <div class="ota-support-submitted-actions">
                    <a href="{{ client_route('support') }}" class="ota-btn-primary">Back to support</a>
                    <a href="{{ client_route('home') }}" class="ota-btn-secondary">Return home</a>
                </div>
            </div>
        </div>
    </section>
@endsection
