@php
    $pp = is_array($pp ?? null) ? $pp : [];
    $type = (string) ($type ?? 'adult');
    $isLead = (bool) ($isLead ?? false);
    $isAdult = $type === 'adult';
    $titles = ['Mr', 'Ms', 'Mrs', 'Mx'];
    $genders = ['M' => 'Male', 'F' => 'Female', 'X' => 'Unspecified'];
    $pkDomesticDocs = (bool) ($pkDomesticDocs ?? false);
    $checkoutCountries = is_array($checkoutCountries ?? null) ? $checkoutCountries : [];
    $checkoutCountryCodes = array_column($checkoutCountries, 'code');
    $ppDoc = (string) ($pp['document_type'] ?? 'passport');
    $showPassportFields = ! $pkDomesticDocs || $ppDoc !== 'national_id';
    $showNationalIdFields = $pkDomesticDocs && $ppDoc === 'national_id';
    $nationalityValue = strtoupper((string) ($pp['nationality'] ?? ''));
    $passportIssuerValue = strtoupper((string) ($pp['passport_issuing_country'] ?? ''));
    $typeLabel = match ($type) {
        'child' => 'Child',
        'infant' => 'Infant',
        default => 'Adult, 12+ years',
    };
@endphp
<article class="ota-mobile-booking__card ota-mobile-booking__pax-card" data-mobile-pax-card>
    <header class="ota-mobile-booking__pax-head">
        <h2 class="ota-mobile-booking__pax-title">Passenger {{ ($pos ?? 0) + 1 }}</h2>
        <span class="ota-mobile-booking__pax-badge">{{ $typeLabel }}</span>
        @if ($isLead)
            <span class="ota-mobile-booking__pax-lead">Lead</span>
        @endif
    </header>

    <input type="hidden" name="passengers[{{ $i }}][passenger_type]" value="{{ $type }}">

    <div class="ota-mobile-booking__field">
        <label class="ota-mobile-booking__label" for="pax-{{ $i }}-title">Title</label>
        <select class="ota-mobile-booking__input js-mobile-pax-title" id="pax-{{ $i }}-title" name="passengers[{{ $i }}][title]" required>
            @foreach ($titles as $t)
                <option value="{{ $t }}" @selected(($pp['title'] ?? 'Mr') === $t)>{{ $t }}</option>
            @endforeach
        </select>
        @error("passengers.$i.title")<p class="ota-mobile-booking__error">{{ $message }}</p>@enderror
    </div>

    <div class="ota-mobile-booking__field">
        <label class="ota-mobile-booking__label" for="pax-{{ $i }}-first">First name</label>
        <input class="ota-mobile-booking__input" id="pax-{{ $i }}-first" type="text" name="passengers[{{ $i }}][first_name]" value="{{ $pp['first_name'] ?? '' }}" required autocomplete="given-name">
        @error("passengers.$i.first_name")<p class="ota-mobile-booking__error">{{ $message }}</p>@enderror
    </div>

    <div class="ota-mobile-booking__field">
        <label class="ota-mobile-booking__label" for="pax-{{ $i }}-last">Last name</label>
        <input class="ota-mobile-booking__input" id="pax-{{ $i }}-last" type="text" name="passengers[{{ $i }}][last_name]" value="{{ $pp['last_name'] ?? '' }}" required autocomplete="family-name">
        @error("passengers.$i.last_name")<p class="ota-mobile-booking__error">{{ $message }}</p>@enderror
    </div>

    <div class="ota-mobile-booking__dob-gender">
        <div class="ota-mobile-booking__field ota-mobile-booking__field--dob">
            <label class="ota-mobile-booking__label" for="pax-{{ $i }}-dob">Date of birth</label>
            <input class="ota-mobile-booking__input" id="pax-{{ $i }}-dob" type="date" name="passengers[{{ $i }}][date_of_birth]" value="{{ $pp['date_of_birth'] ?? '' }}" required>
            @error("passengers.$i.date_of_birth")<p class="ota-mobile-booking__error">{{ $message }}</p>@enderror
        </div>

        <div class="ota-mobile-booking__field ota-mobile-booking__field--gender">
            <label class="ota-mobile-booking__label" for="pax-{{ $i }}-gender">Gender</label>
            <select class="ota-mobile-booking__input js-mobile-pax-gender" id="pax-{{ $i }}-gender" name="passengers[{{ $i }}][gender]" required>
                <option value="" disabled @selected(($pp['gender'] ?? '') === '')>Select</option>
                @foreach ($genders as $gv => $gl)
                    <option value="{{ $gv }}" @selected(($pp['gender'] ?? '') === $gv)>{{ $gl }}</option>
                @endforeach
            </select>
            @error("passengers.$i.gender")<p class="ota-mobile-booking__error">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="ota-mobile-booking__doc-block" data-pk-domestic="{{ $pkDomesticDocs ? '1' : '0' }}">
        @if ($pkDomesticDocs)
            <div class="ota-mobile-booking__field">
                <label class="ota-mobile-booking__label" for="pax-{{ $i }}-doc-type">Document type</label>
                <select class="ota-mobile-booking__input ota-mobile-pax-document-type" id="pax-{{ $i }}-doc-type" name="passengers[{{ $i }}][document_type]">
                    <option value="national_id" @selected($ppDoc === 'national_id')>National ID / CNIC</option>
                    <option value="passport" @selected($ppDoc !== 'national_id')>Passport</option>
                </select>
                @error("passengers.$i.document_type")<p class="ota-mobile-booking__error">{{ $message }}</p>@enderror
            </div>
            <div class="ota-mobile-booking__field js-mobile-pax-national-id {{ $showNationalIdFields ? '' : 'is-hidden' }}">
                <label class="ota-mobile-booking__label" for="pax-{{ $i }}-national-id">National ID / CNIC number</label>
                <input class="ota-mobile-booking__input" id="pax-{{ $i }}-national-id" type="text" name="passengers[{{ $i }}][national_id_number]" value="{{ $pp['national_id_number'] ?? '' }}" autocomplete="off">
                @error("passengers.$i.national_id_number")<p class="ota-mobile-booking__error">{{ $message }}</p>@enderror
            </div>
        @else
            <input type="hidden" name="passengers[{{ $i }}][document_type]" value="passport">
        @endif

        <div class="js-mobile-pax-passport {{ $showPassportFields ? '' : 'is-hidden' }}">
            <div class="ota-mobile-booking__field">
                <label class="ota-mobile-booking__label" for="pax-{{ $i }}-nationality">Nationality</label>
                <select class="ota-mobile-booking__input js-mobile-pax-passport-input" id="pax-{{ $i }}-nationality" name="passengers[{{ $i }}][nationality]" @if(! $pkDomesticDocs || $showPassportFields) required @endif data-pax-passport-required="1">
                    <x-geo.country-select-options :countries="$checkoutCountries" :selected="$nationalityValue" />
                </select>
                @error("passengers.$i.nationality")<p class="ota-mobile-booking__error">{{ $message }}</p>@enderror
            </div>
            <div class="ota-mobile-booking__field">
                <label class="ota-mobile-booking__label" for="pax-{{ $i }}-passport">Passport number</label>
                <input class="ota-mobile-booking__input js-mobile-pax-passport-input" id="pax-{{ $i }}-passport" type="text" name="passengers[{{ $i }}][passport_number]" value="{{ $pp['passport_number'] ?? '' }}" @if(! $pkDomesticDocs || $showPassportFields) required @endif data-pax-passport-required="1">
                @error("passengers.$i.passport_number")<p class="ota-mobile-booking__error">{{ $message }}</p>@enderror
            </div>
            <div class="ota-mobile-booking__field">
                <label class="ota-mobile-booking__label" for="pax-{{ $i }}-passport-country">Issuing country</label>
                <select class="ota-mobile-booking__input js-mobile-pax-passport-input" id="pax-{{ $i }}-passport-country" name="passengers[{{ $i }}][passport_issuing_country]" @if(! $pkDomesticDocs || $showPassportFields) required @endif data-pax-passport-required="1">
                    <x-geo.country-select-options :countries="$checkoutCountries" :selected="$passportIssuerValue" />
                </select>
                @error("passengers.$i.passport_issuing_country")<p class="ota-mobile-booking__error">{{ $message }}</p>@enderror
            </div>
            <div class="ota-mobile-booking__field">
                <label class="ota-mobile-booking__label" for="pax-{{ $i }}-passport-expiry">Expiry date</label>
                <input class="ota-mobile-booking__input js-mobile-pax-passport-input" id="pax-{{ $i }}-passport-expiry" type="date" name="passengers[{{ $i }}][passport_expiry_date]" value="{{ $pp['passport_expiry_date'] ?? '' }}" @if(! $pkDomesticDocs || $showPassportFields) required @endif data-pax-passport-required="1">
                @error("passengers.$i.passport_expiry_date")<p class="ota-mobile-booking__error">{{ $message }}</p>@enderror
            </div>
            <div class="ota-mobile-booking__field">
                <label class="ota-mobile-booking__label" for="pax-{{ $i }}-passport-issue">Issue date</label>
                <input class="ota-mobile-booking__input js-mobile-pax-passport-input" id="pax-{{ $i }}-passport-issue" type="date" name="passengers[{{ $i }}][passport_issue_date]" value="{{ $pp['passport_issue_date'] ?? '' }}" @if(! $pkDomesticDocs || $showPassportFields) required @endif data-pax-passport-required="1">
                @error("passengers.$i.passport_issue_date")<p class="ota-mobile-booking__error">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    @if ($isAdult && ($adultCount ?? 1) > 1)
        <label class="ota-mobile-booking__radio">
            <input type="radio" name="lead_passenger_index" value="{{ $i }}" @checked($isLead)>
            <span>Set as lead passenger</span>
        </label>
    @elseif ($isLead)
        <input type="hidden" name="lead_passenger_index" value="{{ $i }}">
    @endif
</article>
