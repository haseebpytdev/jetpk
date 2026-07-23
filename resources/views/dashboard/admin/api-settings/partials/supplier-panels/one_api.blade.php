<div class="jp-provider-panel {{ ($selectedProvider ?? '') === 'one_api' ? '' : 'jp-is-hidden' }}" data-provider-panel="one_api">
    @if(($selectedProvider ?? '') === 'one_api' && isset($connection) && $connection->exists)
        @php
            $readiness = app(\App\Services\Suppliers\OneApi\Support\OneApiReadinessService::class)->dimensions($connection);
        @endphp
        <div class="jp-card jp-card--muted mb-3">
            <h3 class="jp-card__title h6">One API readiness</h3>
            <ul class="mb-0 small">
                @foreach ($readiness as $dimension)
                    <li>
                        <strong>{{ $dimension['label'] }}:</strong>
                        {{ ($dimension['ready'] ?? false) ? 'Ready' : 'Blocked' }}
                        — {{ $dimension['detail'] }}
                    </li>
                @endforeach
            </ul>
            <p class="form-hint mt-2 mb-0">SOAP URL must be supplied by the vendor. Test Connection never prices, books, or modifies reservations.</p>
        </div>
    @endif
</div>
