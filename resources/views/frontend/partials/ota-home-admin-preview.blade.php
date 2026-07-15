<section class="ota-section ota-home-admin-preview" id="operator">
    <div class="ota-container">
        <header class="ota-section-head ota-section-head--compact">
            <p class="ota-section-kicker">Business travel support</p>
            <h2 class="ota-section-title">Corporate and partner assistance</h2>
            <p class="ota-section-desc">{{ $brandName }} supports corporate accounts, partner agents, and operational booking coordination.</p>
        </header>
        <div class="operator-preview-shell">
            <div class="operator-preview-grid">
                <a href="{{ route('admin.dashboard') }}" class="operator-preview-card">
                    <span class="operator-preview-card-icon" aria-hidden="true"><i class="fa fa-dashboard"></i></span>
                    <span class="operator-preview-card-title">Corporate account support</span>
                    <span class="operator-preview-card-desc">Dedicated handling for recurring business travel needs</span>
                </a>
                <a href="{{ route('admin.agents') }}" class="operator-preview-card">
                    <span class="operator-preview-card-icon" aria-hidden="true"><i class="fa fa-bolt"></i></span>
                    <span class="operator-preview-card-title">Agent partnership desk</span>
                    <span class="operator-preview-card-desc">Fast onboarding and support for partner agencies</span>
                </a>
                <a href="{{ route('admin.bookings') }}" class="operator-preview-card">
                    <span class="operator-preview-card-icon" aria-hidden="true"><i class="fa fa-ticket"></i></span>
                    <span class="operator-preview-card-title">Booking operations</span>
                    <span class="operator-preview-card-desc">End-to-end booking follow-up and traveler assistance</span>
                </a>
                <a href="{{ route('admin.reports') }}" class="operator-preview-card">
                    <span class="operator-preview-card-icon" aria-hidden="true"><i class="fa fa-line-chart"></i></span>
                    <span class="operator-preview-card-title">Travel insights</span>
                    <span class="operator-preview-card-desc">Route demand and business performance visibility</span>
                </a>
            </div>
            <div class="operator-preview-cta">
                <a href="{{ client_route('support') }}" class="ota-btn ota-btn-primary operator-preview-cta-btn">Talk to our team</a>
            </div>
        </div>
    </div>
</section>
