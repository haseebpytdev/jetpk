@extends(client_layout('dashboard', 'admin'))

@section('title', 'Edit page SEO')

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle"><a href="{{ route('admin.seo.pages.index') }}">Page SEO</a></div>
            <h1 class="jp-page-title">{{ $page['label'] }}</h1>
        </div>
        <div class="col-auto ms-auto">
            <a href="{{ $page['public_url'] }}" target="_blank" rel="noopener" class="jp-btn jp-btn--ghost btn-sm">View live</a>
        </div>
    </div>
@endsection

@section('content')
    @include('dashboard.admin.seo.partials.nav', ['seoNav' => 'pages'])

    @if (session('status'))
        <div class="jp-alert jp-alert--success">{{ session('status') }}</div>
    @endif

    <div class="jp-alert jp-alert--info small">
        @if ($page['source_type'] === 'cms')
            CMS SEO updates save immediately to the authoritative CmsPage record.
        @else
            Saving a draft does not change the live site until you publish.
        @endif
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('admin.seo.pages.update', ['sourceType' => $page['source_type'], 'sourceId' => $page['source_id']]) }}" class="jp-stack">
                @csrf
                @method('PATCH')

                <div class="jp-card">
                    <h2 class="jp-card__title">Page info</h2>
                    <dl class="row small mb-0">
                        <dt class="col-sm-3">Public URL</dt><dd class="col-sm-9"><code>{{ $page['public_url'] }}</code></dd>
                        <dt class="col-sm-3">Source</dt><dd class="col-sm-9">{{ ucfirst($page['source_type']) }}</dd>
                        <dt class="col-sm-3">Publish state</dt><dd class="col-sm-9">{{ ($page['meta']['published'] ?? false) ? 'Published' : 'Using fallback / draft' }}</dd>
                        <dt class="col-sm-3">Sitemap</dt><dd class="col-sm-9">{{ ($page['form']['sitemap_eligible'] ?? true) ? 'Eligible when indexable' : 'Excluded' }}</dd>
                    </dl>
                </div>

                <div class="jp-card">
                    <h2 class="jp-card__title">Basic SEO</h2>
                    <label class="jp-label">SEO title <span class="text-muted">(<span id="seo-title-count">0</span> chars)</span></label>
                    <input class="jp-control" name="title" value="{{ old('title', $page['form']['title']) }}" maxlength="180" data-count-target="seo-title-count">
                    <label class="jp-label mt-2">Meta description <span class="text-muted">(<span id="seo-desc-count">0</span> chars)</span></label>
                    <textarea class="jp-control" name="description" rows="3" maxlength="500" data-count-target="seo-desc-count">{{ old('description', $page['form']['description']) }}</textarea>
                    <label class="jp-label mt-2">Canonical URL</label>
                    <input class="jp-control" name="canonical" value="{{ old('canonical', $page['form']['canonical']) }}" placeholder="{{ $page['public_url'] }}">
                    <div class="form-hint">Must use https://jetpakistan.pk or a relative path such as {{ $page['path'] }}.</div>
                    @error('canonical')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>

                <div class="jp-card">
                    <h2 class="jp-card__title">Search engine</h2>
                    <div class="d-flex flex-wrap gap-3">
                        <label class="form-check"><input type="hidden" name="index" value="0"><input type="checkbox" class="form-check-input" name="index" value="1" @checked(old('index', $page['form']['index']))> Index</label>
                        <label class="form-check"><input type="hidden" name="follow" value="0"><input type="checkbox" class="form-check-input" name="follow" value="1" @checked(old('follow', $page['form']['follow']))> Follow</label>
                        @if ($page['source_type'] !== 'cms')
                            <label class="form-check"><input type="hidden" name="sitemap_eligible" value="0"><input type="checkbox" class="form-check-input" name="sitemap_eligible" value="1" @checked(old('sitemap_eligible', $page['form']['sitemap_eligible']))> Sitemap eligible</label>
                        @endif
                    </div>
                </div>

                <div class="jp-card">
                    <h2 class="jp-card__title">Social</h2>
                    <label class="jp-label">OG title</label>
                    <input class="jp-control" name="og_title" value="{{ old('og_title', $page['form']['og_title']) }}">
                    <label class="jp-label mt-2">OG description</label>
                    <textarea class="jp-control" name="og_description" rows="2">{{ old('og_description', $page['form']['og_description']) }}</textarea>
                    <label class="jp-label mt-2">OG image URL or asset key</label>
                    <input class="jp-control" name="og_image" value="{{ old('og_image', $page['form']['og_image']) }}">
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="jp-btn jp-btn--primary">Save draft</button>
                </div>
            </form>
            @if ($page['source_type'] !== 'cms')
                <form method="POST" action="{{ route('admin.seo.pages.publish', ['sourceType' => $page['source_type'], 'sourceId' => $page['source_id']]) }}" class="mt-2">
                    @csrf
                    <button type="submit" class="jp-btn jp-btn--outline">Publish</button>
                </form>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="jp-card">
                <h2 class="jp-card__title">Google preview</h2>
                <div class="small">
                    <div class="text-primary">{{ $page['resolved']['title'] ?? $page['form']['title'] }}</div>
                    <div class="text-success">{{ $page['public_url'] }}</div>
                    <div class="text-muted">{{ $page['resolved']['description'] ?? $page['form']['description'] }}</div>
                </div>
            </div>
            <div class="jp-card mt-3">
                <h2 class="jp-card__title">Live resolved values</h2>
                <dl class="small mb-0">
                    <dt>Title</dt><dd>{{ $page['resolved']['title'] ?? '—' }}</dd>
                    <dt>Robots</dt><dd>{{ $page['resolved']['robots'] ?? '—' }}</dd>
                    <dt>OG title</dt><dd>{{ $page['resolved']['og_title'] ?? '—' }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('[data-count-target]').forEach((el) => {
            const target = document.getElementById(el.dataset.countTarget);
            const update = () => { if (target) target.textContent = String(el.value.length); };
            el.addEventListener('input', update);
            update();
        });
    </script>
@endsection
