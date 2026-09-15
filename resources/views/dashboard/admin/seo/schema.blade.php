@extends(client_layout('dashboard', 'admin'))

@section('title', 'Schema & Business Data')

@section('page-header')
    <div class="jp-between"><div class="col"><div class="page-pretitle"><a href="{{ route('admin.seo.overview') }}">SEO Management</a></div><h1 class="jp-page-title">Schema & Business Data</h1></div></div>
@endsection

@section('content')
    @include('dashboard.admin.seo.partials.nav', ['seoNav' => 'schema'])
    <div class="jp-alert jp-alert--info small">{{ $schema['note'] }}</div>
    <div class="jp-card">
        <h2 class="jp-card__title">Structured data sources</h2>
        <dl class="row mb-0">
            <dt class="col-sm-3">Brand</dt><dd class="col-sm-9">{{ $schema['brand_name'] }}</dd>
            <dt class="col-sm-3">Legal name</dt><dd class="col-sm-9">{{ $schema['legal_name'] ?: '—' }}</dd>
            <dt class="col-sm-3">Phone</dt><dd class="col-sm-9">{{ $schema['phone'] ?: '—' }}</dd>
            <dt class="col-sm-3">Email</dt><dd class="col-sm-9">{{ $schema['email'] ?: '—' }}</dd>
            <dt class="col-sm-3">Office</dt><dd class="col-sm-9">{{ $schema['office'] ?: '—' }}</dd>
            <dt class="col-sm-3">Hours</dt><dd class="col-sm-9">{{ $schema['hours'] ?: '—' }}</dd>
            <dt class="col-sm-3">Website</dt><dd class="col-sm-9">{{ $schema['website'] }}</dd>
            <dt class="col-sm-3">JSON-LD types</dt><dd class="col-sm-9">{{ implode(', ', $schema['schema_types']) }}</dd>
        </dl>
    </div>
    <a href="{{ client_route('admin.page-settings.edit', ['pageKey' => 'global']) }}" class="jp-btn jp-btn--outline">Edit contact in Page settings → Global</a>
@endsection
