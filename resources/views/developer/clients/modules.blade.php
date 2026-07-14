@extends('layouts.developer')

@section('title', 'Modules — '.$profile->name)

@section('page-header')
    <h1 class="ota-dev-cp-page-title h2 mb-1">{{ $profile->name }} — Modules</h1>
    <p class="text-secondary mb-0">Per-client module toggles for <code>{{ $profile->slug }}</code></p>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @include('developer.clients.partials.tabs', ['profile' => $profile, 'activeTab' => 'modules'])

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('dev.cp.clients.modules.update', $profile) }}">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    @foreach ($moduleKeys as $moduleKey)
                        <div class="col-md-4">
                            <label class="form-check">
                                <input type="hidden" name="modules[{{ $moduleKey }}]" value="0">
                                <input type="checkbox" name="modules[{{ $moduleKey }}]" value="1" class="form-check-input"
                                       @checked(old('modules.'.$moduleKey, $modules[$moduleKey] ?? false))>
                                <span class="form-check-label"><code>{{ $moduleKey }}</code></span>
                            </label>
                        </div>
                    @endforeach
                </div>
                @if ($profile->is_master_profile)
                    <div class="mt-4">
                        <label class="form-check">
                            <input type="checkbox" name="confirm_master_edit" value="1" class="form-check-input @error('confirm_master_edit') is-invalid @enderror"
                                   @checked(old('confirm_master_edit') === '1')>
                            <span class="form-check-label">I confirm editing the master deployment profile</span>
                            @error('confirm_master_edit')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </label>
                    </div>
                @endif
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Save modules</button>
                </div>
            </form>
        </div>
    </div>
@endsection
