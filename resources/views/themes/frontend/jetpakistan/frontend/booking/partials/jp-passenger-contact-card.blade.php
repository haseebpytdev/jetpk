@php
    use App\Support\Travel\TravelDocumentFormatter;

    $jpPassengers = $jpPassengers ?? collect();
    $jpDraft = is_array($jpDraft ?? null) ? $jpDraft : [];
    $jpPassengerCounts = is_array($jpPassengerCounts ?? null) ? $jpPassengerCounts : ['total' => 0, 'adults' => 0, 'children' => 0, 'infants' => 0];
@endphp

<article class="jp-checkout-card jp-checkout-card--passengers" data-jp-passenger-summary>
    <h2 class="jp-checkout-card__title">Passenger &amp; contact</h2>
    <p class="jp-checkout-card__lead">
        {{ $jpPassengerCounts['total'] }} {{ $jpPassengerCounts['total'] === 1 ? 'passenger' : 'passengers' }}
        ({{ $jpPassengerCounts['adults'] }} {{ $jpPassengerCounts['adults'] === 1 ? 'adult' : 'adults' }},
        {{ $jpPassengerCounts['children'] }} {{ $jpPassengerCounts['children'] === 1 ? 'child' : 'children' }},
        {{ $jpPassengerCounts['infants'] }} {{ $jpPassengerCounts['infants'] === 1 ? 'infant' : 'infants' }})
    </p>

    <div class="jp-passenger-list">
        @foreach ($jpPassengers as $idx => $passenger)
            <section class="jp-passenger-block">
                <header class="jp-passenger-block__head">
                    <span class="jp-passenger-block__index">Passenger {{ $idx + 1 }}</span>
                    <span class="jp-passenger-block__type">{{ ucfirst((string) $passenger->passenger_type) }}</span>
                    @if ($passenger->is_lead_passenger)
                        <span class="jp-passenger-block__badge">Lead</span>
                    @endif
                </header>
                <dl class="jp-kv-grid jp-kv-grid--passenger">
                    <div class="jp-kv-grid__row">
                        <dt>Name</dt>
                        <dd>{{ trim(($passenger->title ?? '').' '.($passenger->first_name ?? '').' '.($passenger->last_name ?? '')) }}</dd>
                    </div>
                    @if ($passenger->date_of_birth)
                        <div class="jp-kv-grid__row">
                            <dt>Date of birth</dt>
                            <dd>{{ $passenger->date_of_birth->format('j M Y') }}</dd>
                        </div>
                    @endif
                    @if ($passenger->nationality)
                        <div class="jp-kv-grid__row">
                            <dt>Nationality</dt>
                            <dd>{{ strtoupper($passenger->nationality) }}</dd>
                        </div>
                    @endif
                    @if ($passenger->passport_number || $passenger->national_id_number)
                        <div class="jp-kv-grid__row">
                            <dt>Travel document</dt>
                            <dd>
                                @if ($passenger->document_type === 'national_id' && $passenger->national_id_number)
                                    {{ TravelDocumentFormatter::maskPassport($passenger->national_id_number) }} (National ID)
                                @elseif ($passenger->passport_number)
                                    {{ TravelDocumentFormatter::maskPassport($passenger->passport_number) }}
                                    @if ($passenger->passport_issuing_country)
                                        · {{ strtoupper($passenger->passport_issuing_country) }}
                                    @endif
                                    @if ($passenger->passport_expiry_date)
                                        · expires {{ $passenger->passport_expiry_date->format('j M Y') }}
                                    @endif
                                @endif
                            </dd>
                        </div>
                    @endif
                </dl>
            </section>
        @endforeach
    </div>

    <section class="jp-contact-block">
        <h3 class="jp-contact-block__title">Contact details</h3>
        <dl class="jp-kv-grid jp-kv-grid--contact">
            <div class="jp-kv-grid__row">
                <dt>Email</dt>
                <dd>{{ $jpDraft['email'] ?? '—' }}</dd>
            </div>
            <div class="jp-kv-grid__row">
                <dt>Mobile</dt>
                <dd>{{ $jpDraft['phone'] ?? '—' }}</dd>
            </div>
            <div class="jp-kv-grid__row">
                <dt>Country</dt>
                <dd>{{ ! empty($jpDraft['country']) ? $jpDraft['country'] : '—' }}</dd>
            </div>
        </dl>
    </section>
</article>
