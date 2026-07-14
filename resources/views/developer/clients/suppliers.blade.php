@extends('layouts.developer')

@section('title', 'Suppliers — '.$profile->name)

@section('page-header')
    <h1 class="ota-dev-cp-page-title h2 mb-1">{{ $profile->name }} — Suppliers</h1>
    <p class="text-secondary mb-0">Supplier enablement for <code>{{ $profile->slug }}</code>. Credentials are stored encrypted and never exported.</p>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @include('developer.clients.partials.tabs', ['profile' => $profile, 'activeTab' => 'suppliers'])

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('dev.cp.clients.suppliers.update', $profile) }}">
                @csrf
                @method('PUT')
                @foreach ($suppliers as $supplierKey => $supplier)
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <h3 class="h4 mb-0"><code>{{ $supplierKey }}</code></h3>
                            <label class="form-check mb-0">
                                <input type="hidden" name="suppliers[{{ $supplierKey }}][enabled]" value="0">
                                <input type="checkbox" name="suppliers[{{ $supplierKey }}][enabled]" value="1" class="form-check-input"
                                       @checked(old('suppliers.'.$supplierKey.'.enabled', $supplier->enabled))>
                                <span class="form-check-label">Enabled</span>
                            </label>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="mode_{{ $supplierKey }}">Mode</label>
                                <select name="suppliers[{{ $supplierKey }}][mode]" id="mode_{{ $supplierKey }}" class="form-select">
                                    <option value="">—</option>
                                    @foreach (\App\Enums\SupplierEnvironment::cases() as $env)
                                        <option value="{{ $env->value }}" @selected(old('suppliers.'.$supplierKey.'.mode', $supplier->mode) === $env->value)>{{ $env->value }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Stored credentials (masked)</label>
                                @php $masked = $supplier->maskedCredentials(); @endphp
                                @if ($masked === [])
                                    <div class="text-secondary small">No credentials stored.</div>
                                @else
                                    <ul class="small mb-0">
                                        @foreach ($masked as $credKey => $credValue)
                                            <li><code>{{ $credKey }}</code>: {{ $credValue !== '' ? $credValue : '—' }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="cred_client_id_{{ $supplierKey }}">Update client_id (optional)</label>
                                <input type="password" name="suppliers[{{ $supplierKey }}][credentials][client_id]" id="cred_client_id_{{ $supplierKey }}"
                                       class="form-control" autocomplete="new-password" maxlength="500">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="cred_client_secret_{{ $supplierKey }}">Update client_secret (optional)</label>
                                <input type="password" name="suppliers[{{ $supplierKey }}][credentials][client_secret]" id="cred_client_secret_{{ $supplierKey }}"
                                       class="form-control" autocomplete="new-password" maxlength="500">
                            </div>
                        </div>
                    </div>
                @endforeach
                @if ($profile->is_master_profile)
                    <div class="mb-3">
                        <label class="form-check">
                            <input type="checkbox" name="confirm_master_edit" value="1" class="form-check-input @error('confirm_master_edit') is-invalid @enderror"
                                   @checked(old('confirm_master_edit') === '1')>
                            <span class="form-check-label">I confirm editing the master deployment profile</span>
                            @error('confirm_master_edit')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </label>
                    </div>
                @endif
                <button type="submit" class="btn btn-primary">Save suppliers</button>
            </form>
        </div>
    </div>
@endsection
