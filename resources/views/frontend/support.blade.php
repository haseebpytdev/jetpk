@extends(client_layout('frontend', 'frontend'))

@php
    $brand = config('ota-brand', []);
    $client = config('ota-client', []);
    $brandName = $client['agency_name'] ?? ($brand['product_name'] ?? config('app.name'));
    $supportContact = $publicAgencyContact ?? \App\Support\Branding\PublicAgencyContactResolver::resolve($agencySettings ?? null);
    $supportEmail = $supportContact->email;
    $supportPhone = $supportContact->phone;
    $supportWhatsapp = $supportContact->whatsapp;
    $isJetPakistanTheme = is_client_preview() && client_theme()->frontendTheme() === 'jetpakistan';
    if ($isJetPakistanTheme) {
        $brandName = 'JetPakistan';
        $supportEmail = 'ota@jetpakistan.pk';
        $supportPhone = '0311 1222427';
        $supportWhatsapp = '923111222427';
    }
@endphp

@section('title', 'Support & contact - '.$brandName)

@section('content')
    <section class="ota-section ota-form-page ota-support-page" aria-labelledby="ota-support-heading">
        <div class="ota-container">
            @auth
                @if (auth()->user()->isCustomer())
                    <div class="ota-page-wrap ota-account-page" style="padding-bottom: 0;">
                        <div class="ota-account-wrap" style="max-width: 1080px; margin: 0 auto; padding-top: 0;">
                            @include('layouts.partials.customer-account-nav')
                            <div class="ota-account-help-grid mb-4">
                                <div class="ota-account-help-card">
                                    <h3>How to book</h3>
                                    <p>Search flights, compare fares, and complete checkout with traveler details.</p>
                                    <a href="{{ client_route('home') }}#jp-flight-search" class="ota-account-btn ota-account-btn--secondary ota-account-btn--sm">Search flights</a>
                                </div>
                                <div class="ota-account-help-card">
                                    <h3>Payment help</h3>
                                    <p>Upload payment proof from My bookings when your trip is awaiting verification.</p>
                                    <a href="{{ route('customer.bookings.index', ['filter' => 'pending_payment']) }}" class="ota-account-btn ota-account-btn--secondary ota-account-btn--sm">Pending payments</a>
                                </div>
                                <div class="ota-account-help-card">
                                    <h3>Refunds &amp; cancellations</h3>
                                    <p>Request changes or cancellations from your booking detail page.</p>
                                    <a href="{{ route('customer.bookings.index') }}" class="ota-account-btn ota-account-btn--secondary ota-account-btn--sm">My bookings</a>
                                </div>
                                <div class="ota-account-help-card">
                                    <h3>Contact support</h3>
                                    <p>Open a support ticket linked to your booking for structured help.</p>
                                    <a href="{{ route('customer.support.tickets.create') }}" class="ota-account-btn ota-account-btn--primary ota-account-btn--sm">Create support ticket</a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            @endauth
            <header class="ota-section-head ota-support-hero">
                <p class="ota-section-kicker">Support &amp; contact</p>
                <h1 id="ota-support-heading" class="ota-section-title ota-support-page-title">Help and Support Center</h1>
                <p class="ota-section-desc ota-support-hero-desc">
                    @if($isJetPakistanTheme)
                        Contact JetPakistan for flight changes, cancellations, invoice questions, booking feedback, and general travel support. The same OTA support workflow is used, with JetPakistan channels and branding.
                    @else
                        Find answers quickly, send a support request, and get guided assistance for flights, payments, travel documents, and partnerships.
                    @endif
                </p>
            </header>

            <div class="row ota-support-main-row align-items-start g-4 mx-0" style="margin-left:0!important;margin-right:0!important;width:100%;max-width:100%;box-sizing:border-box;">
                <div class="col-12 col-lg-6 ota-support-col-info">
                    <div class="ota-support-panel">
                        <h2 class="ota-support-panel-title">Help categories</h2>
                        <ul class="ota-support-list">
                            <li>Booking status</li>
                            <li>Payment proof</li>
                            <li>E-ticket support</li>
                            <li>Cancellations</li>
                        </ul>
                    </div>
                    <div class="ota-support-panel">
                        <h2 class="ota-support-panel-title">FAQs</h2>
                        <div class="ota-support-faq">
                            <p class="ota-support-faq-item"><strong>How fast do you respond?</strong><br>Most requests are answered within 2-6 business hours.</p>
                            <p class="ota-support-faq-item"><strong>Can I get help on WhatsApp?</strong><br>Yes, use WhatsApp for urgent travel updates.</p>
                            <p class="ota-support-faq-item"><strong>Can I edit a booking after submission?</strong><br>Yes, contact support with booking reference and requested changes.</p>
                        </div>
                    </div>
                    <div class="ota-support-panel">
                        <h2 class="ota-support-panel-title">Office &amp; channels</h2>
                        <p class="ota-support-panel-lead">
                            @if($isJetPakistanTheme)
                                JetPakistan assists customers with online flight booking, domestic and international routes, ticket changes, cancellations, invoices, and booking questions.
                            @else
                                {{ $brandName }} helps customers and partners with flight booking, support operations, and travel servicing.
                            @endif
                        </p>
                        <ul class="ota-support-list ota-support-list--channels">
                            @if($supportPhone !== '')
                                <li><span class="ota-support-channel-label">Phone:</span> {{ $supportPhone }}</li>
                            @endif
                            @if($supportWhatsapp !== '')
                                <li><span class="ota-support-channel-label">WhatsApp:</span> <a href="https://wa.me/{{ $supportWhatsapp }}" target="_blank" rel="noopener">Chat on WhatsApp</a></li>
                            @endif
                            @if($supportEmail !== '')
                                <li><span class="ota-support-channel-label">Email:</span> <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a></li>
                            @endif
                            @php $officeCity = $client['office_city'] ?? ''; @endphp
                            @if($isJetPakistanTheme)
                                <li><span class="ota-support-channel-label">Office:</span> Office No. 220, 2nd Floor, Century Tower, Kalma Chowk, Gulberg III, Lahore</li>
                                <li><span class="ota-support-channel-label">Website:</span> <a href="https://www.jetpakistan.com" target="_blank" rel="noopener">www.jetpakistan.com</a></li>
                            @elseif($officeCity !== '')
                                <li><span class="ota-support-channel-label">City:</span> {{ $officeCity }}</li>
                            @endif
                        </ul>
                    </div>
                </div>
                <div class="col-12 col-lg-6 ota-support-col-forms">
                    <div class="ota-form-card ota-support-form-card" data-support-premium-form>
                        <h3 class="ota-support-form-card-title">Support request</h3>
                        <p class="ota-support-form-card-desc">Share your issue and our team will respond shortly.</p>
                        @if ($errors->getBag('supportRequest')->any())
                            <div class="ota-alert ota-alert--warning mb-3" role="alert">
                                <ul class="mb-0 ps-3">
                                    @foreach ($errors->getBag('supportRequest')->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <form class="ota-form-grid" action="{{ route('support.store') }}" method="post" novalidate>
                            @csrf
                            <input type="hidden" name="form_type" value="support">
                            <div class="ota-visually-hidden" aria-hidden="true">
                                <label for="support-website">Website</label>
                                <input id="support-website" name="website" type="text" tabindex="-1" autocomplete="off">
                            </div>
                            @guest
                                <div class="ota-field">
                                    <label class="ota-label" for="support-name">Your name</label>
                                    <input id="support-name" name="name" class="ota-input @error('name', 'supportRequest') ota-input--invalid @enderror" type="text" value="{{ old('name') }}" placeholder="Full name" autocomplete="name" required>
                                    @error('name', 'supportRequest')<p class="ota-field-error">{{ $message }}</p>@enderror
                                </div>
                            @endguest
                            <div class="ota-field">
                                <label class="ota-label" for="support-email">Email</label>
                                <input id="support-email" name="email" class="ota-input @error('email', 'supportRequest') ota-input--invalid @enderror" type="email" value="{{ old('email', auth()->user()?->email) }}" placeholder="you@example.com" autocomplete="email" required>
                                @error('email', 'supportRequest')<p class="ota-field-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="ota-field">
                                <label class="ota-label" for="support-subject">Subject</label>
                                <input id="support-subject" name="subject" class="ota-input @error('subject', 'supportRequest') ota-input--invalid @enderror" type="text" value="{{ old('subject') }}" placeholder="Brief summary of your issue" maxlength="200" required>
                                @error('subject', 'supportRequest')<p class="ota-field-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="ota-field">
                                <label class="ota-label" for="support-ref">Booking reference (optional)</label>
                                <input id="support-ref" name="booking_reference" class="ota-input @error('booking_reference', 'supportRequest') ota-input--invalid @enderror" type="text" value="{{ old('booking_reference') }}" placeholder="e.g. ATR-123456" autocomplete="off" maxlength="64">
                                @error('booking_reference', 'supportRequest')<p class="ota-field-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="ota-field">
                                <label class="ota-label" for="support-category">Issue type</label>
                                <select id="support-category" name="category" class="ota-select @error('category', 'supportRequest') ota-input--invalid @enderror" required>
                                    <option value="" disabled @selected(old('category') === null)>Select issue type</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->value }}" @selected(old('category') === $category->value)>{{ $category->label() }}</option>
                                    @endforeach
                                </select>
                                @error('category', 'supportRequest')<p class="ota-field-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="ota-field">
                                <label class="ota-label" for="support-message">Message</label>
                                <textarea id="support-message" name="body" class="ota-textarea @error('body', 'supportRequest') ota-input--invalid @enderror" rows="4" placeholder="Describe your support request" maxlength="5000" required>{{ old('body') }}</textarea>
                                @error('body', 'supportRequest')<p class="ota-field-error">{{ $message }}</p>@enderror
                            </div>
                            <x-turnstile />
                            <button type="submit" class="ota-btn-primary ota-btn-primary--block ota-support-submit">Submit support request</button>
                        </form>
                        <div class="ota-support-form-links">
                            @if($supportWhatsapp !== '')
                                <p class="ota-support-form-links-line">Fast channel: <a href="https://wa.me/{{ $supportWhatsapp }}" target="_blank" rel="noopener">WhatsApp support</a></p>
                            @endif
                            <p class="ota-support-form-links-line">Need status fast? <a href="{{ client_route('booking.lookup') }}">Manage booking</a>.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
