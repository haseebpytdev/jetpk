@extends('layouts.developer')

@section('title', 'Branding — '.$profile->name)

@section('page-header')
    <h1 class="ota-dev-cp-page-title h2 mb-1">{{ $profile->name }} — Branding</h1>
    <p class="text-secondary mb-0">Company identity and contact details for <code>{{ $profile->slug }}</code></p>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @include('developer.clients.partials.tabs', ['profile' => $profile, 'activeTab' => 'branding'])

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('dev.cp.clients.branding.update', $profile) }}" class="row g-3">
                @csrf
                @method('PUT')
                <div class="col-md-6">
                    <label class="form-label" for="company_name">Company name</label>
                    <input type="text" name="company_name" id="company_name" class="form-control @error('company_name') is-invalid @enderror"
                           value="{{ old('company_name', $branding?->company_name ?? $profile->name) }}" required maxlength="255">
                    @error('company_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror"
                           value="{{ old('email', $branding?->email) }}" maxlength="255">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="logo_path">Logo path</label>
                    <input type="text" name="logo_path" id="logo_path" class="form-control @error('logo_path') is-invalid @enderror"
                           value="{{ old('logo_path', $branding?->logo_path) }}" maxlength="512">
                    @error('logo_path')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="favicon_path">Favicon path</label>
                    <input type="text" name="favicon_path" id="favicon_path" class="form-control @error('favicon_path') is-invalid @enderror"
                           value="{{ old('favicon_path', $branding?->favicon_path) }}" maxlength="512">
                    @error('favicon_path')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="primary_color">Primary color</label>
                    <input type="text" name="primary_color" id="primary_color" class="form-control @error('primary_color') is-invalid @enderror"
                           value="{{ old('primary_color', $branding?->primary_color) }}" maxlength="32">
                    @error('primary_color')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="secondary_color">Secondary color</label>
                    <input type="text" name="secondary_color" id="secondary_color" class="form-control @error('secondary_color') is-invalid @enderror"
                           value="{{ old('secondary_color', $branding?->secondary_color) }}" maxlength="32">
                    @error('secondary_color')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="accent_color">Accent color</label>
                    <input type="text" name="accent_color" id="accent_color" class="form-control @error('accent_color') is-invalid @enderror"
                           value="{{ old('accent_color', $branding?->accent_color) }}" maxlength="32">
                    @error('accent_color')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="phone">Phone</label>
                    <input type="text" name="phone" id="phone" class="form-control @error('phone') is-invalid @enderror"
                           value="{{ old('phone', $branding?->phone) }}" maxlength="64">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="address">Address</label>
                    <textarea name="address" id="address" rows="2" class="form-control @error('address') is-invalid @enderror">{{ old('address', $branding?->address) }}</textarea>
                    @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="footer_text">Footer text</label>
                    <textarea name="footer_text" id="footer_text" rows="2" class="form-control @error('footer_text') is-invalid @enderror">{{ old('footer_text', $branding?->footer_text) }}</textarea>
                    @error('footer_text')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Save branding</button>
                </div>
            </form>
        </div>
    </div>
@endsection
