@php
    $isEdit = $traveler->exists;
    $useAccountForm = $useAccountForm ?? false;
    $countries = is_array($countries ?? null) ? $countries : [];
@endphp

@if ($useAccountForm)
    <div class="ota-account-form-grid">
        <div class="ota-account-form-section">
            <h3 class="ota-account-form-section__title">Personal details</h3>
            <div class="ota-account-form-grid ota-account-form-grid--2">
                <div class="ota-account-field ota-account-field--sm">
                    <label class="form-label" for="title">Title</label>
                    <select name="title" id="title" class="form-select" required>
                        @foreach (['Mr', 'Mrs', 'Ms', 'Miss', 'Dr'] as $t)
                            <option value="{{ $t }}" @selected(old('title', $traveler->title) === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ota-account-field ota-account-field--sm">
                    <label class="form-label" for="gender">Gender</label>
                    <select name="gender" id="gender" class="form-select @error('gender') is-invalid @enderror" required>
                        <option value="" disabled @selected(old('gender', $traveler->gender) === '')>Select</option>
                        @foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $val => $label)
                            <option value="{{ $val }}" @selected(old('gender', $traveler->gender) === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="ota-account-field">
                    <label class="form-label" for="first_name">First name</label>
                    <input type="text" name="first_name" id="first_name" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name', $traveler->first_name) }}" required>
                    @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="ota-account-field">
                    <label class="form-label" for="last_name">Last name</label>
                    <input type="text" name="last_name" id="last_name" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name', $traveler->last_name) }}" required>
                    @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="ota-account-field">
                    <label class="form-label" for="date_of_birth">Date of birth</label>
                    <input type="date" name="date_of_birth" id="date_of_birth" class="form-control @error('date_of_birth') is-invalid @enderror" value="{{ old('date_of_birth', $traveler->date_of_birth?->format('Y-m-d')) }}" required>
                    @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="ota-account-field">
                    <label class="form-label" for="nationality">Nationality</label>
                    <select name="nationality" id="nationality" class="form-select @error('nationality') is-invalid @enderror" required>
                        <x-geo.country-select-options :countries="$countries" :selected="old('nationality', $traveler->nationality)" />
                    </select>
                    @error('nationality')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="ota-account-form-section">
            <h3 class="ota-account-form-section__title">Travel document</h3>
            <div class="ota-account-form-grid ota-account-form-grid--2">
                <div class="ota-account-field">
                    <label class="form-label" for="document_type">Document type</label>
                    <select name="document_type" id="document_type" class="form-select @error('document_type') is-invalid @enderror" required>
                        <option value="" disabled @selected(old('document_type', $traveler->document_type) === '')>Select</option>
                        <option value="passport" @selected(old('document_type', $traveler->document_type) === 'passport')>Passport</option>
                        <option value="national_id" @selected(old('document_type', $traveler->document_type) === 'national_id')>National ID</option>
                    </select>
                    @error('document_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="ota-account-field">
                    <label class="form-label" for="document_number">Document number</label>
                    <input type="text" name="document_number" id="document_number" class="form-control @error('document_number') is-invalid @enderror" value="{{ old('document_number') }}" autocomplete="off" @if($isEdit) placeholder="Leave blank to keep current number" @endif>
                    @if ($isEdit && $traveler->maskedDocumentNumber())
                        <div class="form-text">Current on file: {{ $traveler->maskedDocumentNumber() }}</div>
                    @endif
                    @error('document_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="ota-account-field">
                    <label class="form-label" for="issuing_country">Issuing country</label>
                    <select name="issuing_country" id="issuing_country" class="form-select @error('issuing_country') is-invalid @enderror">
                        <x-geo.country-select-options :countries="$countries" :selected="old('issuing_country', $traveler->issuing_country)" />
                    </select>
                    @error('issuing_country')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="ota-account-field">
                    <label class="form-label" for="document_expiry">Document expiry</label>
                    <input type="date" name="document_expiry" id="document_expiry" class="form-control @error('document_expiry') is-invalid @enderror" value="{{ old('document_expiry', $traveler->document_expiry?->format('Y-m-d')) }}">
                    @error('document_expiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="ota-account-form-section">
            <h3 class="ota-account-form-section__title">Contact details</h3>
            <div class="ota-account-form-grid ota-account-form-grid--2">
                <div class="ota-account-field">
                    <label class="form-label" for="email">Email (optional)</label>
                    <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $traveler->email) }}">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="ota-account-field">
                    <label class="form-label" for="phone">Phone (optional)</label>
                    <input type="text" name="phone" id="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $traveler->phone) }}">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>
@else
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="title">Title</label>
            <select name="title" id="title" class="form-select" required>
                @foreach (['Mr', 'Mrs', 'Ms', 'Miss', 'Dr'] as $t)
                    <option value="{{ $t }}" @selected(old('title', $traveler->title) === $t)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="first_name">First name</label>
            <input type="text" name="first_name" id="first_name" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name', $traveler->first_name) }}" required>
            @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="last_name">Last name</label>
            <input type="text" name="last_name" id="last_name" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name', $traveler->last_name) }}" required>
            @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="gender">Gender</label>
            <select name="gender" id="gender" class="form-select @error('gender') is-invalid @enderror" required>
                <option value="" disabled @selected(old('gender', $traveler->gender) === '')>Select</option>
                @foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $val => $label)
                    <option value="{{ $val }}" @selected(old('gender', $traveler->gender) === $val)>{{ $label }}</option>
                @endforeach
            </select>
            @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="date_of_birth">Date of birth</label>
            <input type="date" name="date_of_birth" id="date_of_birth" class="form-control @error('date_of_birth') is-invalid @enderror" value="{{ old('date_of_birth', $traveler->date_of_birth?->format('Y-m-d')) }}" required>
            @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="nationality">Nationality</label>
            <select name="nationality" id="nationality" class="form-select @error('nationality') is-invalid @enderror" required>
                <x-geo.country-select-options :countries="$countries" :selected="old('nationality', $traveler->nationality)" />
            </select>
            @error('nationality')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="document_type">Document type</label>
            <select name="document_type" id="document_type" class="form-select @error('document_type') is-invalid @enderror" required>
                <option value="" disabled @selected(old('document_type', $traveler->document_type) === '')>Select</option>
                <option value="passport" @selected(old('document_type', $traveler->document_type) === 'passport')>Passport</option>
                <option value="national_id" @selected(old('document_type', $traveler->document_type) === 'national_id')>National ID</option>
            </select>
            @error('document_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="document_number">Document number</label>
            <input type="text" name="document_number" id="document_number" class="form-control @error('document_number') is-invalid @enderror" value="{{ old('document_number') }}" autocomplete="off" @if($isEdit) placeholder="Leave blank to keep current number" @endif>
            @if ($isEdit && $traveler->maskedDocumentNumber())
                <div class="form-text">Current on file: {{ $traveler->maskedDocumentNumber() }}</div>
            @endif
            @error('document_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="document_expiry">Document expiry</label>
            <input type="date" name="document_expiry" id="document_expiry" class="form-control @error('document_expiry') is-invalid @enderror" value="{{ old('document_expiry', $traveler->document_expiry?->format('Y-m-d')) }}">
            @error('document_expiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="issuing_country">Issuing country</label>
            <select name="issuing_country" id="issuing_country" class="form-select @error('issuing_country') is-invalid @enderror">
                <x-geo.country-select-options :countries="$countries" :selected="old('issuing_country', $traveler->issuing_country)" />
            </select>
            @error('issuing_country')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="email">Contact email (optional)</label>
            <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $traveler->email) }}">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="phone">Contact phone (optional)</label>
            <input type="text" name="phone" id="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $traveler->phone) }}">
            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label class="form-check">
                <input type="checkbox" name="is_default" value="1" class="form-check-input" @checked(old('is_default', $traveler->is_default))>
                <span class="form-check-label">Default traveler</span>
            </label>
        </div>
    </div>
@endif
