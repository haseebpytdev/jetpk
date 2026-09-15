@extends(client_layout('dashboard', 'admin'))

@section('title', 'Global SEO')

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle"><a href="{{ route('admin.seo.overview') }}">SEO Management</a></div>
            <h1 class="jp-page-title">Global SEO</h1>
        </div>
    </div>
@endsection

@section('content')
    @include('dashboard.admin.seo.partials.nav', ['seoNav' => 'global'])

    @if (session('status'))
        <div class="jp-alert jp-alert--success">{{ session('status') }}</div>
    @endif

    <div class="jp-alert jp-alert--info small">
        Precedence: page explicit SEO → global SEO → application fallback. Global values apply when a page leaves a field empty.
    </div>

    <form method="POST" action="{{ route('admin.seo.global.update') }}" class="jp-stack">
        @csrf
        @method('PATCH')
        <div class="jp-card">
            <div class="jp-form-grid">
                <div><label class="jp-label">Brand / site name</label><input class="jp-control" name="brand_name" value="{{ old('brand_name', $settings['brand_name']) }}"></div>
                <div><label class="jp-label">Title suffix</label><input class="jp-control" name="title_suffix" value="{{ old('title_suffix', $settings['title_suffix']) }}" placeholder="JetPakistan"></div>
                <div class="col-12"><label class="jp-label">Default SEO title</label><input class="jp-control" name="title" value="{{ old('title', $settings['title']) }}"></div>
                <div class="col-12"><label class="jp-label">Default meta description</label><textarea class="jp-control" rows="3" name="description">{{ old('description', $settings['description']) }}</textarea></div>
                <div class="col-12"><label class="jp-label">Canonical domain</label><input class="jp-control" name="canonical_domain" value="{{ old('canonical_domain', $settings['canonical_domain']) }}"><div class="form-hint">Must remain https://jetpakistan.pk</div></div>
                <div><label class="jp-label">Default robots</label><input class="jp-control" name="robots" value="{{ old('robots', $settings['robots']) }}"></div>
            </div>
        </div>
        <div class="jp-card">
            <h2 class="jp-card__title">Default Open Graph</h2>
            <label class="jp-label">OG title</label><input class="jp-control" name="og_title" value="{{ old('og_title', $settings['og_title']) }}">
            <label class="jp-label mt-2">OG description</label><textarea class="jp-control" rows="2" name="og_description">{{ old('og_description', $settings['og_description']) }}</textarea>
            <label class="jp-label mt-2">OG image</label><input class="jp-control" name="og_image" value="{{ old('og_image', $settings['og_image']) }}">
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="jp-btn jp-btn--primary">Save draft</button>
        </div>
    </form>
    <form method="POST" action="{{ route('admin.seo.global.publish') }}" class="mt-2">
        @csrf
        <button type="submit" class="jp-btn jp-btn--outline">Publish global SEO</button>
    </form>
@endsection
