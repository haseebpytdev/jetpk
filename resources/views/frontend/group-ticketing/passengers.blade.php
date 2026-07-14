@extends(client_layout('frontend', 'frontend'))

@section('title', 'Group booking — passengers')

@section('content')
    @php
        $maxSeats = $inventory->availableSeats();
        $initialSeats = max(1, min($maxSeats, (int) old('seat_count', $seatCount)));
        $checkoutCountries = is_array($checkoutCountries ?? null) ? $checkoutCountries : [];
        $checkoutSummary = is_array($checkoutSummary ?? null) ? $checkoutSummary : [];
        $totalAmount = (float) $inventory->price * $initialSeats;
    @endphp
    <div class="ota-book-wrap ota-checkout-page ota-checkout-page--group" data-checkout-page>
        <div class="ota-container ota-container-wide">
            @include('frontend.checkout.partials.shell', [
                'productLabel' => 'Group Ticketing',
                'title' => 'Complete your booking',
                'lead' => null,
                'activeStep' => $activeStep ?? 'passengers',
            ])

            <div class="ota-checkout-grid ota-booking-layout">
                <div class="ota-checkout-main">
                    <form method="POST" action="{{ route('group-ticketing.booking.passengers.store', $inventory) }}" id="gt-passengers-form" class="ota-checkout-form" data-checkout-passenger-form>
                        @csrf

                        <div class="ota-checkout-card ota-checkout-card--section">
                            <h2 class="ota-checkout-section-title">Seats</h2>
                            <div class="ota-form-group mb-0">
                                <label class="ota-label" for="seat_count">Number of seats</label>
                                <select id="seat_count" name="seat_count" class="form-control ota-input" required data-max-seats="{{ $maxSeats }}">
                                    @for ($n = 1; $n <= $maxSeats; $n++)
                                        <option value="{{ $n }}" @selected($initialSeats === $n)>{{ $n }}</option>
                                    @endfor
                                </select>
                                @error('seat_count')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <h2 class="ota-checkout-section-title ota-checkout-travellers-heading">Travellers <span class="ota-checkout-travellers-count" id="gt-travellers-count">{{ $initialSeats }}</span></h2>

                        <div id="gt-passenger-blocks">
                            @for ($i = 0; $i < $initialSeats; $i++)
                                @include('frontend.group-ticketing.partials.passenger-block', [
                                    'index' => $i,
                                    'countries' => $checkoutCountries,
                                    'open' => $i === 0 || $errors->any(),
                                ])
                            @endfor
                        </div>
                        @error('passengers')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

                        @include('frontend.checkout.partials.contact-card')

                        <div class="ota-checkout-submit-bar ota-booking-actions">
                            <button type="submit" class="ota-btn-primary-lg btn btn-lg btn-block">Continue to review</button>
                        </div>
                    </form>
                </div>

                @include('frontend.checkout.partials.summary-card', [
                    'summary' => $checkoutSummary,
                    'seatCount' => $initialSeats,
                    'totalAmount' => $totalAmount,
                    'showPayNote' => true,
                ])
            </div>
        </div>
    </div>

    <template id="gt-passenger-block-template">
        @include('frontend.group-ticketing.partials.passenger-block', [
            'index' => '__INDEX__',
            'countries' => $checkoutCountries,
            'open' => true,
        ])
    </template>
@endsection

@push('scripts')
<script>
(function () {
    var seatSelect = document.getElementById('seat_count');
    var blocks = document.getElementById('gt-passenger-blocks');
    var template = document.getElementById('gt-passenger-block-template');
    var countEl = document.getElementById('gt-travellers-count');
    if (!seatSelect || !blocks || !template) return;

    function syncDocumentType(card) {
        if (!card) return;
        var select = card.querySelector('.js-gt-document-type');
        if (!select) return;
        var idx = select.getAttribute('data-passenger-index');
        var isNationalId = select.value === 'national_id';
        var issueField = card.querySelector('.js-gt-passport-only[data-passenger-index="' + idx + '"]');
        var numberLabel = card.querySelector('.js-gt-doc-number-label[data-passenger-index="' + idx + '"]');
        var expiryLabel = card.querySelector('.js-gt-doc-expiry-label[data-passenger-index="' + idx + '"]');
        var issueInput = card.querySelector('.js-gt-passport-issue');
        if (issueField) issueField.hidden = isNationalId;
        if (issueInput) issueInput.required = !isNationalId;
        if (numberLabel) numberLabel.textContent = isNationalId ? 'ID number' : 'Passport number';
        if (expiryLabel) expiryLabel.textContent = isNationalId ? 'ID expiry date' : 'Passport expiry date';
    }

    function bindDocumentTypeHandlers(root) {
        (root || document).querySelectorAll('.js-gt-document-type').forEach(function (select) {
            if (select._gtDocBound) return;
            select._gtDocBound = true;
            select.addEventListener('change', function () {
                syncDocumentType(select.closest('.ota-passenger-card'));
            });
            syncDocumentType(select.closest('.ota-passenger-card'));
        });
    }

    function rebuild(count) {
        var html = template.innerHTML;
        blocks.innerHTML = '';
        for (var i = 0; i < count; i++) {
            blocks.insertAdjacentHTML('beforeend', html.replace(/__INDEX__/g, String(i)));
        }
        if (countEl) countEl.textContent = String(count);
        bindDocumentTypeHandlers(blocks);
    }

    seatSelect.addEventListener('change', function () {
        rebuild(parseInt(seatSelect.value, 10) || 1);
    });

    bindDocumentTypeHandlers(blocks);
})();
</script>
@endpush
