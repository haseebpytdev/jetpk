@php $c = $client ?? config('ota-client', []); @endphp
<section class="ota-section ota-home-whitelabel" id="whitelabel">
    <div class="ota-container">
        <div class="white-label-card">
            <div class="white-label-layout">
                <div class="white-label-main">
                    <p class="ota-section-kicker">Branding</p>
                    <h2 class="white-label-title">Your brand. Your domain. Our engine.</h2>
                    <p class="white-label-lead">
                        This storefront experience uses <strong>{{ $c['agency_name'] ?? 'your agency' }}</strong> naming, colors, and support touchpoints while
                        Hayat Travel Solutions stays visible only where it should — as platform credit.
                    </p>
                    <ul class="white-label-bullets">
                        <li><i class="fa fa-check" aria-hidden="true"></i> Primary color injection for CTAs &amp; accents</li>
                        <li><i class="fa fa-check" aria-hidden="true"></i> Branded header, footer, and onboarding support flow</li>
                        <li><i class="fa fa-check" aria-hidden="true"></i> Operator console separated at <code>/admin</code></li>
                    </ul>
                    <a href="{{ client_route('support') }}" class="ota-btn ota-btn-primary white-label-cta">Talk to our team</a>
                </div>
                <aside class="white-label-panel" aria-label="Branding details">
                    <div class="white-label-panel-head">Branding panel</div>
                    <dl class="white-label-dl">
                        <dt>Agency name</dt>
                        <dd>{{ $c['agency_name'] ?? '—' }}</dd>
                        <dt>Domain</dt>
                        <dd>{{ $c['domain_preview'] ?? '—' }}</dd>
                        <dt>Primary color</dt>
                        <dd class="white-label-dd-color">
                            <span class="ota-color-swatch" style="background:{{ $c['primary_color'] ?? '#0c4a6e' }}"></span>
                            <span>{{ $c['primary_color'] ?? '—' }}</span>
                        </dd>
                        <dt>WhatsApp</dt>
                        <dd>{{ ($w = (string) ($c['support_whatsapp'] ?? '')) !== '' ? '+'.ltrim($w, '+') : '—' }}</dd>
                        <dt>Footer text</dt>
                        <dd class="white-label-dd-muted">{{ \Illuminate\Support\Str::limit((string) ($c['footer_text'] ?? ''), 120) }}</dd>
                    </dl>
                    <p class="ota-powered-by ota-powered-by--panel">Powered by {{ $c['powered_by'] ?? 'Hayat Travel Solutions' }}</p>
                </aside>
            </div>
        </div>
    </div>
</section>
