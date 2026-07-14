<div class="jp-portal-support">
    <h3>Need help with your trip?</h3>
    <p>Our support team is available around the clock for booking questions and changes.</p>
    <div class="jp-portal-support__actions">
        <a href="{{ client_route('support') }}" class="jp-portal-btn jp-portal-btn--primary jp-portal-btn--sm">
            <x-jp.icon name="phone" /> Contact support
        </a>
        @if (Route::has('customer.support.tickets.create'))
            <a href="{{ client_route('customer.support.tickets.create') }}" class="jp-portal-btn jp-portal-btn--ghost jp-portal-btn--sm">
                <x-jp.icon name="chat" /> Open a ticket
            </a>
        @endif
    </div>
</div>
