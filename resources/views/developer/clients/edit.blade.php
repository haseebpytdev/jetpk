@extends('layouts.developer')

@section('title', 'Edit '.$profile->name)

@section('page-header')
    <h1 class="ota-dev-cp-page-title h2 mb-1">{{ $profile->name }}</h1>
    <p class="text-secondary mb-0">General settings for <code>{{ $profile->slug }}</code></p>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @include('developer.clients.partials.tabs', ['profile' => $profile, 'activeTab' => 'general'])

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('dev.cp.clients.update', $profile) }}" class="row g-3">
                @csrf
                @method('PUT')
                <div class="col-md-6">
                    <label class="form-label" for="name">Name</label>
                    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $profile->name) }}" required maxlength="255">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Slug</label>
                    <input type="text" class="form-control" value="{{ $profile->slug }}" readonly disabled>
                    <div class="form-hint">Slug is immutable after create.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="domain">Domain</label>
                    <input type="text" name="domain" id="domain" class="form-control @error('domain') is-invalid @enderror"
                           value="{{ old('domain', $profile->domain) }}" maxlength="255">
                    @error('domain')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="environment">Environment</label>
                    <select name="environment" id="environment" class="form-select @error('environment') is-invalid @enderror">
                        @foreach (['production', 'staging', 'development'] as $env)
                            <option value="{{ $env }}" @selected(old('environment', $profile->environment) === $env)>{{ $env }}</option>
                        @endforeach
                    </select>
                    @error('environment')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="default_locale">Default locale</label>
                    <input type="text" name="default_locale" id="default_locale" class="form-control @error('default_locale') is-invalid @enderror"
                           value="{{ old('default_locale', $profile->default_locale) }}" required maxlength="16">
                    @error('default_locale')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="timezone">Timezone</label>
                    <input type="text" name="timezone" id="timezone" class="form-control @error('timezone') is-invalid @enderror"
                           value="{{ old('timezone', $profile->timezone) }}" required maxlength="64">
                    @error('timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="currency">Currency</label>
                    <input type="text" name="currency" id="currency" class="form-control @error('currency') is-invalid @enderror"
                           value="{{ old('currency', $profile->currency) }}" required maxlength="8">
                    @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-check">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active', $profile->is_active))>
                        <span class="form-check-label">Active</span>
                    </label>
                </div>
                @if ($profile->is_master_profile)
                    <div class="col-12">
                        <label class="form-check">
                            <input type="checkbox" name="confirm_master_edit" value="1" class="form-check-input @error('confirm_master_edit') is-invalid @enderror"
                                   @checked(old('confirm_master_edit') === '1')>
                            <span class="form-check-label">I confirm editing the master deployment profile</span>
                            @error('confirm_master_edit')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </label>
                    </div>
                @endif
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save general</button>
                    <a href="{{ route('dev.cp.clients.index') }}" class="btn btn-outline-secondary">Back to list</a>
                </div>
            </form>
        </div>
    </div>
@endsection
