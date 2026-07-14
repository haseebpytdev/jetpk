@php
    /** @var int|string $index */
    $i = is_numeric($index) ? (int) $index : 0;
    $prefix = (string) ($prefix ?? 'passengers');
    $countries = is_array($countries ?? null) ? $countries : [];
    $open = (bool) ($open ?? ($i === 0));
    $displayNum = $i + 1;
    $pp = old($prefix.'.'.$i, []);
    if (! is_array($pp)) {
        $pp = [];
    }
    $titles = ['Mr', 'Mrs', 'Ms', 'Miss', 'Dr', 'Mstr'];
    $genders = ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'];
    $docType = (string) ($pp['document_type'] ?? 'passport');
    $nationalityValue = strtoupper((string) ($pp['nationality'] ?? 'PK'));
    if ($nationalityValue === '' || strlen($nationalityValue) > 3) {
        $nationalityValue = 'PK';
    }
@endphp
<details class="ota-checkout-card ota-checkout-card--section ota-passenger-card" data-passenger-index="{{ $i }}" @if ($open) open @endif>
    <summary class="ota-passenger-card__summary">
        <span class="ota-passenger-card__title">
            <span class="ota-passenger-card__index">{{ $displayNum }}</span>
            Passenger {{ $displayNum }}
        </span>
        <span class="ota-passenger-card__chevron" aria-hidden="true"></span>
    </summary>
    <div class="ota-passenger-card__body">
        <input type="hidden" name="{{ $prefix }}[{{ $i }}][passenger_type]" value="adult">

        <section class="ota-pax-section">
            <h3 class="ota-pax-section__title">Passenger details</h3>
            <div class="ota-pax-grid ota-pax-grid--identity">
                <div class="ota-pax-field ota-pax-field--title">
                    <div class="ota-form-group">
                        <label class="ota-label">Title</label>
                        <select class="form-control ota-input" name="{{ $prefix }}[{{ $i }}][title]" required>
                            @foreach ($titles as $t)
                                <option value="{{ $t }}" @selected(($pp['title'] ?? 'Mr') === $t)>{{ $t }}</option>
                            @endforeach
                        </select>
                        @error("{$prefix}.{$i}.title")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="ota-pax-field ota-pax-field--fname">
                    <div class="ota-form-group">
                        <label class="ota-label">Given name</label>
                        <input class="form-control ota-input" type="text" name="{{ $prefix }}[{{ $i }}][first_name]" value="{{ $pp['first_name'] ?? '' }}" required autocomplete="given-name">
                        @error("{$prefix}.{$i}.first_name")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="ota-pax-field ota-pax-field--lname">
                    <div class="ota-form-group">
                        <label class="ota-label">Surname</label>
                        <input class="form-control ota-input" type="text" name="{{ $prefix }}[{{ $i }}][last_name]" value="{{ $pp['last_name'] ?? '' }}" required autocomplete="family-name">
                        @error("{$prefix}.{$i}.last_name")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="ota-pax-field ota-pax-field--dob">
                    <div class="ota-form-group">
                        <label class="ota-label">Date of birth</label>
                        <input class="form-control ota-input" type="date" name="{{ $prefix }}[{{ $i }}][date_of_birth]" value="{{ $pp['date_of_birth'] ?? '' }}" required>
                        @error("{$prefix}.{$i}.date_of_birth")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="ota-pax-field ota-pax-field--gender">
                    <div class="ota-form-group">
                        <label class="ota-label">Gender</label>
                        <select class="form-control ota-input" name="{{ $prefix }}[{{ $i }}][gender]" required>
                            <option value="" disabled @selected(($pp['gender'] ?? '') === '')>Select</option>
                            @foreach ($genders as $gv => $gl)
                                <option value="{{ $gv }}" @selected(($pp['gender'] ?? '') === $gv)>{{ $gl }}</option>
                            @endforeach
                        </select>
                        @error("{$prefix}.{$i}.gender")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="ota-pax-field ota-pax-field--nationality">
                    <div class="ota-form-group">
                        <label class="ota-label">Nationality</label>
                        <select class="form-control ota-input ota-checkout-country-select" name="{{ $prefix }}[{{ $i }}][nationality]" required>
                            <x-geo.country-select-options :countries="$countries" :selected="$nationalityValue" />
                        </select>
                        @error("{$prefix}.{$i}.nationality")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </section>

        <section class="ota-pax-section">
            <h3 class="ota-pax-section__title">Travel document</h3>
            <div class="ota-pax-grid ota-pax-grid--passport">
                <div class="ota-pax-field ota-pax-field--doc-type">
                    <div class="ota-form-group">
                        <label class="ota-label">Document type</label>
                        <select class="form-control ota-input js-gt-document-type" name="{{ $prefix }}[{{ $i }}][document_type]" required data-passenger-index="{{ $i }}">
                            @foreach (['passport' => 'Passport', 'national_id' => 'National ID'] as $val => $label)
                                <option value="{{ $val }}" @selected($docType === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error("{$prefix}.{$i}.document_type")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="ota-pax-field ota-pax-field--passport-no">
                    <div class="ota-form-group">
                        <label class="ota-label js-gt-doc-number-label" data-passenger-index="{{ $i }}">{{ $docType === 'national_id' ? 'ID number' : 'Passport number' }}</label>
                        <input class="form-control ota-input" type="text" name="{{ $prefix }}[{{ $i }}][passport_number]" value="{{ $pp['passport_number'] ?? '' }}" required>
                        @error("{$prefix}.{$i}.passport_number")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="ota-pax-field ota-pax-field--passport-issue js-gt-passport-only" data-passenger-index="{{ $i }}" @if ($docType === 'national_id') hidden @endif>
                    <div class="ota-form-group">
                        <label class="ota-label">Passport issue date</label>
                        <input class="form-control ota-input js-gt-passport-issue" type="date" name="{{ $prefix }}[{{ $i }}][passport_issue_date]" value="{{ $pp['passport_issue_date'] ?? '' }}">
                        @error("{$prefix}.{$i}.passport_issue_date")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="ota-pax-field ota-pax-field--passport-expiry js-gt-passport-expiry-field" data-passenger-index="{{ $i }}">
                    <div class="ota-form-group">
                        <label class="ota-label js-gt-doc-expiry-label" data-passenger-index="{{ $i }}">{{ $docType === 'national_id' ? 'ID expiry date' : 'Passport expiry date' }}</label>
                        <input class="form-control ota-input js-gt-passport-expiry" type="date" name="{{ $prefix }}[{{ $i }}][passport_expiry]" value="{{ $pp['passport_expiry'] ?? '' }}" required>
                        @error("{$prefix}.{$i}.passport_expiry")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </section>
    </div>
</details>
