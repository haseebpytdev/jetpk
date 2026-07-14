@extends(client_layout('dashboard', 'admin'))

@section('title', 'Create Supplier Connection')

@section('page-header')
    <div class="jp-between">
        <div>
            <p class="jp-backlink"><a href="{{ client_route('admin.api-settings') }}">← Supplier connections</a></p>
            <h1>{{ ($showProviderPicker ?? false) ? 'Choose a provider' : 'Create supplier connection' }}</h1>
            <p>{{ ($showProviderPicker ?? false) ? 'Select a GDS, NDC, or API channel. Credentials are encrypted at rest.' : 'Configure environment, credentials, and connection status.' }}</p>
        </div>
    </div>
@endsection

@section('content')
@include('themes.admin.jetpakistan.partials.flash')

@if ($showProviderPicker ?? false)
    @include('themes.admin.jetpakistan.api-settings.provider-picker', ['providers' => $providerCards ?? []])
@else
    <div class="jp-card jp-module-compat jp-supplier-form-shell">
        <p class="jp-backlink"><a href="{{ client_route('admin.api-settings.create') }}">← Change provider</a></p>
        @include('dashboard.admin.api-settings.form')
    </div>
@endif
@endsection
