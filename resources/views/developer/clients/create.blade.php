@extends('layouts.developer')

@section('title', 'Create Client Profile')

@section('page-header')
    <h1 class="ota-dev-cp-page-title h2 mb-1">Create client profile</h1>
    <p class="text-secondary mb-0">Add a new deployment profile. Slug cannot be changed after create.</p>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('dev.cp.clients.store') }}" class="row g-3">
                @csrf
                <div class="col-md-6">
                    <label class="form-label" for="name">Name</label>
                    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name') }}" required maxlength="255">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="slug">Slug</label>
                    <input type="text" name="slug" id="slug" class="form-control @error('slug') is-invalid @enderror"
                           value="{{ old('slug') }}" required maxlength="255" pattern="[A-Za-z0-9_-]+">
                    @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="domain">Domain</label>
                    <input type="text" name="domain" id="domain" class="form-control @error('domain') is-invalid @enderror"
                           value="{{ old('domain') }}" maxlength="255">
                    @error('domain')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="environment">Environment</label>
                    <select name="environment" id="environment" class="form-select @error('environment') is-invalid @enderror">
                        @foreach (['production', 'staging', 'development'] as $env)
                            <option value="{{ $env }}" @selected(old('environment', 'production') === $env)>{{ $env }}</option>
                        @endforeach
                    </select>
                    @error('environment')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="default_locale">Default locale</label>
                    <input type="text" name="default_locale" id="default_locale" class="form-control @error('default_locale') is-invalid @enderror"
                           value="{{ old('default_locale', 'en') }}" required maxlength="16">
                    @error('default_locale')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="timezone">Timezone</label>
                    <input type="text" name="timezone" id="timezone" class="form-control @error('timezone') is-invalid @enderror"
                           value="{{ old('timezone', 'Asia/Karachi') }}" required maxlength="64">
                    @error('timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="currency">Currency</label>
                    <input type="text" name="currency" id="currency" class="form-control @error('currency') is-invalid @enderror"
                           value="{{ old('currency', 'PKR') }}" required maxlength="8">
                    @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="active_frontend_theme">Frontend theme</label>
                    <input type="text" name="active_frontend_theme" id="active_frontend_theme" class="form-control @error('active_frontend_theme') is-invalid @enderror"
                           value="{{ old('active_frontend_theme', 'v1-classic') }}" maxlength="64">
                    @error('active_frontend_theme')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="asset_profile">Asset profile</label>
                    <input type="text" name="asset_profile" id="asset_profile" class="form-control @error('asset_profile') is-invalid @enderror"
                           value="{{ old('asset_profile') }}" maxlength="255" placeholder="Defaults to slug">
                    @error('asset_profile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-check">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active', true))>
                        <span class="form-check-label">Active</span>
                    </label>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Create client</button>
                    <a href="{{ route('dev.cp.clients.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
