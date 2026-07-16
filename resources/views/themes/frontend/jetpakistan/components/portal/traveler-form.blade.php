{{-- JP-PORTAL-3 · Shared JetPK portal traveler form (Customer + Agent + Agent Staff)
     Included by:
       themes/customer/jetpakistan/travelers/{create,edit}.blade.php
       themes/agent/jetpakistan/travelers/{create,edit}.blade.php

     WHY SHARED: the legacy fallback dashboard.travelers._form renders ONE identical field set for
     both roles whenever $useAccountForm === true (the portal branch). Customer and Agent portal
     contracts are therefore provably compatible and no role-specific behaviour is lost by sharing.

     RECOMPOSED into JetPK vocabulary. Preserved verbatim from dashboard.travelers._form
     (useAccountForm === true branch):
       • field names: title, gender, first_name, last_name, date_of_birth, nationality,
         document_type, document_number, issuing_country, document_expiry, email, phone
       • every old() default and its second argument
       • every @error block
       • every `required` attribute (title, gender, first_name, last_name, date_of_birth,
         nationality, document_type) — document_number/issuing_country/document_expiry/email/phone
         remain OPTIONAL exactly as in legacy
       • title options: Mr, Mrs, Ms, Miss, Dr
       • gender options: male|Male, female|Female, other|Other + disabled "Select" placeholder
       • document_type options: passport|Passport, national_id|National ID + disabled placeholder
       • <x-geo.country-select-options> for nationality AND issuing_country (canonical — reused)
       • document_number: value=old('document_number') ONLY (never echoes stored number),
         autocomplete="off", edit-only placeholder, edit-only masked "Current on file" hint
       • $isEdit derived from $traveler->exists

     DELIBERATELY ABSENT: `is_default`. The legacy portal branch does NOT expose it — only the
     non-portal (layouts.dashboard) branch does. Adding it here would invent a field the portal
     contract never had. Do not add it.

     SECURITY: document_number is never pre-filled with the real value; only maskedDocumentNumber()
     is ever rendered. This mirrors legacy exactly.
--}}
@php
    $isEdit = $traveler->exists;
    $countries = is_array($countries ?? null) ? $countries : [];
@endphp

<div class="jp-form">
    <section class="jp-form__section">
        <h3 class="jp-form__section-title">Personal details</h3>
        <div class="jp-form__grid jp-form__grid--2">
            <div class="jp-field jp-field--sm">
                <label class="jp-label" for="title">Title</label>
                <select name="title" id="title" class="jp-select" required>
                    @foreach (['Mr', 'Mrs', 'Ms', 'Miss', 'Dr'] as $t)
                        <option value="{{ $t }}" @selected(old('title', $traveler->title) === $t)>{{ $t }}</option>
                    @endforeach
                </select>
            </div>

            <div class="jp-field jp-field--sm">
                <label class="jp-label" for="gender">Gender</label>
                <select name="gender" id="gender" class="jp-select @error('gender') is-invalid @enderror" required>
                    <option value="" disabled @selected(old('gender', $traveler->gender) === '')>Select</option>
                    @foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $val => $label)
                        <option value="{{ $val }}" @selected(old('gender', $traveler->gender) === $val)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('gender')<p class="jp-field__error">{{ $message }}</p>@enderror
            </div>

            <div class="jp-field">
                <label class="jp-label" for="first_name">First name</label>
                <input type="text" name="first_name" id="first_name" class="jp-input @error('first_name') is-invalid @enderror" value="{{ old('first_name', $traveler->first_name) }}" required>
                @error('first_name')<p class="jp-field__error">{{ $message }}</p>@enderror
            </div>

            <div class="jp-field">
                <label class="jp-label" for="last_name">Last name</label>
                <input type="text" name="last_name" id="last_name" class="jp-input @error('last_name') is-invalid @enderror" value="{{ old('last_name', $traveler->last_name) }}" required>
                @error('last_name')<p class="jp-field__error">{{ $message }}</p>@enderror
            </div>

            <div class="jp-field">
                <label class="jp-label" for="date_of_birth">Date of birth</label>
                <input type="date" name="date_of_birth" id="date_of_birth" class="jp-input @error('date_of_birth') is-invalid @enderror" value="{{ old('date_of_birth', $traveler->date_of_birth?->format('Y-m-d')) }}" required>
                @error('date_of_birth')<p class="jp-field__error">{{ $message }}</p>@enderror
            </div>

            <div class="jp-field">
                <label class="jp-label" for="nationality">Nationality</label>
                <select name="nationality" id="nationality" class="jp-select @error('nationality') is-invalid @enderror" required>
                    <x-geo.country-select-options :countries="$countries" :selected="old('nationality', $traveler->nationality)" />
                </select>
                @error('nationality')<p class="jp-field__error">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>

    <section class="jp-form__section">
        <h3 class="jp-form__section-title">Travel document</h3>
        <div class="jp-form__grid jp-form__grid--2">
            <div class="jp-field">
                <label class="jp-label" for="document_type">Document type</label>
                <select name="document_type" id="document_type" class="jp-select @error('document_type') is-invalid @enderror" required>
                    <option value="" disabled @selected(old('document_type', $traveler->document_type) === '')>Select</option>
                    <option value="passport" @selected(old('document_type', $traveler->document_type) === 'passport')>Passport</option>
                    <option value="national_id" @selected(old('document_type', $traveler->document_type) === 'national_id')>National ID</option>
                </select>
                @error('document_type')<p class="jp-field__error">{{ $message }}</p>@enderror
            </div>

            <div class="jp-field">
                <label class="jp-label" for="document_number">Document number</label>
                <input type="text" name="document_number" id="document_number" class="jp-input @error('document_number') is-invalid @enderror" value="{{ old('document_number') }}" autocomplete="off" @if($isEdit) placeholder="Leave blank to keep current number" @endif>
                @if ($isEdit && $traveler->maskedDocumentNumber())
                    <p class="jp-field__help">Current on file: {{ $traveler->maskedDocumentNumber() }}</p>
                @endif
                @error('document_number')<p class="jp-field__error">{{ $message }}</p>@enderror
            </div>

            <div class="jp-field">
                <label class="jp-label" for="issuing_country">Issuing country</label>
                <select name="issuing_country" id="issuing_country" class="jp-select @error('issuing_country') is-invalid @enderror">
                    <x-geo.country-select-options :countries="$countries" :selected="old('issuing_country', $traveler->issuing_country)" />
                </select>
                @error('issuing_country')<p class="jp-field__error">{{ $message }}</p>@enderror
            </div>

            <div class="jp-field">
                <label class="jp-label" for="document_expiry">Document expiry</label>
                <input type="date" name="document_expiry" id="document_expiry" class="jp-input @error('document_expiry') is-invalid @enderror" value="{{ old('document_expiry', $traveler->document_expiry?->format('Y-m-d')) }}">
                @error('document_expiry')<p class="jp-field__error">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>

    <section class="jp-form__section">
        <h3 class="jp-form__section-title">Contact details</h3>
        <div class="jp-form__grid jp-form__grid--2">
            <div class="jp-field">
                <label class="jp-label" for="email">Email (optional)</label>
                <input type="email" name="email" id="email" class="jp-input @error('email') is-invalid @enderror" value="{{ old('email', $traveler->email) }}">
                @error('email')<p class="jp-field__error">{{ $message }}</p>@enderror
            </div>

            <div class="jp-field">
                <label class="jp-label" for="phone">Phone (optional)</label>
                <input type="text" name="phone" id="phone" class="jp-input @error('phone') is-invalid @enderror" value="{{ old('phone', $traveler->phone) }}">
                @error('phone')<p class="jp-field__error">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>
</div>
