{{-- One API checkout extras: bundles, baggage, meals, seats --}}
@php
    $workflowContextId = $workflowContextId ?? '';
    $supplierConnectionId = (int) ($supplierConnectionId ?? data_get($o ?? [], 'supplier_connection_id', 0));
@endphp
<div
    class="one-api-checkout-extras"
    data-one-api-checkout
    data-workflow-context-id="{{ $workflowContextId }}"
    data-supplier-connection-id="{{ $supplierConnectionId }}"
    data-catalog-url="{{ client_route('booking.one-api.catalog') }}"
    data-final-price-url="{{ client_route('booking.one-api.final-price') }}"
>
    <h2 class="jp-section-title">Customize your trip</h2>
    <p class="text-secondary small" data-one-api-status>Loading airline extras…</p>

    <div class="jp-card mb-3" data-one-api-bundles>
        <h3 class="h6">Fare bundles</h3>
    </div>

    <div class="jp-card mb-3" data-one-api-baggage>
        <h3 class="h6">Baggage</h3>
    </div>

    <div class="jp-card mb-3" data-one-api-meals>
        <h3 class="h6">Meals</h3>
    </div>

    <div class="jp-card mb-3" data-one-api-seats>
        <h3 class="h6">Seats</h3>
    </div>

    <button type="button" class="jp-btn jp-btn--primary mb-3" data-one-api-confirm-price>Confirm final price</button>

    @if (!empty($holdDeadline))
        <p class="alert alert-warning small">On-hold deadline: {{ $holdDeadline }}</p>
    @endif
</div>
@push('scripts')
    <script src="{{ asset('js/ota-one-api-checkout.js') }}?v=2" defer></script>
@endpush
