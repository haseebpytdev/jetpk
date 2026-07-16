@php
    $isEdit = $traveler->exists;
    $countries = is_array($countries ?? null) ? $countries : [];
@endphp

<section class="ota-mobile-customer__card">
    <h2 class="ota-mobile-customer__card-title">Personal details</h2>
    <div class="ota-mobile-customer__field">
        <label class="ota-mobile-customer__label" for="title">Title</label>
        <select name="title" id="title" class="ota-mobile-customer__input" required>
            @foreach (['Mr', 'Mrs', 'Ms', 'Miss', 'Dr'] as $t)
                <option value="{{ $t }}" @selected(old('title', $traveler->title) === $t)>{{ $t }}</option>
            @endforeach
        </select>
    </div>
    <div class="ota-mobile-customer__field">
        <label class="ota-mobile-customer__label" for="gender">Gender</label>
        <select name="gender" id="gender" class="ota-mobile-customer__input @error('gender') is-invalid @enderror" required>
            <option value="" disabled @selected(old('gender', $traveler->gender) === '')>Select</option>
            @foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $val => $label)
                <option value="{{ $val }}" @selected(old('gender', $traveler->gender) === $val)>{{ $label }}</option>
            @endforeach
        </select>
        @error('gender')<p class="ota-mobile-customer__error">{{ $message }}</p>@enderror
    </div>
    <div class="ota-mobile-customer__field">
        <label class="ota-mobile-customer__label" for="first_name">First name</label>
        <input type="text" name="first_name" id="first_name" required class="ota-mobile-customer__input @error('first_name') is-invalid @enderror" value="{{ old('first_name', $traveler->first_name) }}">
        @error('first_name')<p class="ota-mobile-customer__error">{{ $message }}</p>@enderror
    </div>
    <div class="ota-mobile-customer__field">
        <label class="ota-mobile-customer__label" for="last_name">Last name</label>
        <input type="text" name="last_name" id="last_name" required class="ota-mobile-customer__input @error('last_name') is-invalid @enderror" value="{{ old('last_name', $traveler->last_name) }}">
        @error('last_name')<p class="ota-mobile-customer__error">{{ $message }}</p>@enderror
    </div>
    <div class="ota-mobile-customer__field">
        <label class="ota-mobile-customer__label" for="date_of_birth">Date of birth</label>
        <input type="date" name="date_of_birth" id="date_of_birth" class="ota-mobile-customer__input @error('date_of_birth') is-invalid @enderror" value="{{ old('date_of_birth', $traveler->date_of_birth?->format('Y-m-d')) }}" required>
        @error('date_of_birth')<p class="ota-mobile-customer__error">{{ $message }}</p>@enderror
    </div>
    <div class="ota-mobile-customer__field">
        <label class="ota-mobile-customer__label" for="nationality">Nationality</label>
        <select name="nationality" id="nationality" class="ota-mobile-customer__input @error('nationality') is-invalid @enderror" required>
            <x-geo.country-select-options :countries="$countries" :selected="old('nationality', $traveler->nationality)" />
        </select>
        @error('nationality')<p class="ota-mobile-customer__error">{{ $message }}</p>@enderror
    </div>
</section>

<section class="ota-mobile-customer__card">
    <h2 class="ota-mobile-customer__card-title">Travel document</h2>
    <div class="ota-mobile-customer__field">
        <label class="ota-mobile-customer__label" for="document_type">Document type</label>
        <select name="document_type" id="document_type" class="ota-mobile-customer__input @error('document_type') is-invalid @enderror" required>
            <option value="" disabled @selected(old('document_type', $traveler->document_type) === '')>Select</option>
            <option value="passport" @selected(old('document_type', $traveler->document_type) === 'passport')>Passport</option>
            <option value="national_id" @selected(old('document_type', $traveler->document_type) === 'national_id')>National ID</option>
        </select>
        @error('document_type')<p class="ota-mobile-customer__error">{{ $message }}</p>@enderror
    </div>
    <div class="ota-mobile-customer__field">
        <label class="ota-mobile-customer__label" for="document_number">Document number</label>
        <input type="text" name="document_number" id="document_number" autocomplete="off" class="ota-mobile-customer__input @error('document_number') is-invalid @enderror" value="{{ old('document_number') }}" @if($isEdit) placeholder="Leave blank to keep current number" @endif>
        @if ($isEdit && $traveler->maskedDocumentNumber())
            <p class="ota-mobile-customer__note">Current on file: {{ $traveler->maskedDocumentNumber() }}</p>
        @endif
        @error('document_number')<p class="ota-mobile-customer__error">{{ $message }}</p>@enderror
    </div>
    <div class="ota-mobile-customer__field">
        <label class="ota-mobile-customer__label" for="issuing_country">Issuing country</label>
        <select name="issuing_country" id="issuing_country" class="ota-mobile-customer__input @error('issuing_country') is-invalid @enderror">
            <x-geo.country-select-options :countries="$countries" :selected="old('issuing_country', $traveler->issuing_country)" />
        </select>
        @error('issuing_country')<p class="ota-mobile-customer__error">{{ $message }}</p>@enderror
    </div>
    <div class="ota-mobile-customer__field">
        <label class="ota-mobile-customer__label" for="document_expiry">Document expiry</label>
        <input type="date" name="document_expiry" id="document_expiry" class="ota-mobile-customer__input @error('document_expiry') is-invalid @enderror" value="{{ old('document_expiry', $traveler->document_expiry?->format('Y-m-d')) }}">
        @error('document_expiry')<p class="ota-mobile-customer__error">{{ $message }}</p>@enderror
    </div>
</section>

<section class="ota-mobile-customer__card">
    <h2 class="ota-mobile-customer__card-title">Contact details</h2>
    <div class="ota-mobile-customer__field">
        <label class="ota-mobile-customer__label" for="email">Email (optional)</label>
        <input type="email" name="email" id="email" class="ota-mobile-customer__input @error('email') is-invalid @enderror" value="{{ old('email', $traveler->email) }}">
        @error('email')<p class="ota-mobile-customer__error">{{ $message }}</p>@enderror
    </div>
    <div class="ota-mobile-customer__field">
        <label class="ota-mobile-customer__label" for="phone">Phone (optional)</label>
        <input type="text" name="phone" id="phone" class="ota-mobile-customer__input @error('phone') is-invalid @enderror" value="{{ old('phone', $traveler->phone) }}">
        @error('phone')<p class="ota-mobile-customer__error">{{ $message }}</p>@enderror
    </div>
</section>
