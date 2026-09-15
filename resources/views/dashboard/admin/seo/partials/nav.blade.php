@php
    $current = $seoNav ?? '';
    $tabs = [
        ['key' => 'overview', 'label' => 'Overview', 'route' => 'admin.seo.overview'],
        ['key' => 'pages', 'label' => 'Page SEO', 'route' => 'admin.seo.pages.index'],
        ['key' => 'global', 'label' => 'Global SEO', 'route' => 'admin.seo.global'],
        ['key' => 'social', 'label' => 'Social & Open Graph', 'route' => 'admin.seo.social'],
        ['key' => 'schema', 'label' => 'Schema & Business Data', 'route' => 'admin.seo.schema'],
        ['key' => 'sitemap', 'label' => 'Sitemap & Indexing', 'route' => 'admin.seo.sitemap'],
        ['key' => 'verification', 'label' => 'Search Engine Verification', 'route' => 'admin.seo.verification'],
        ['key' => 'audit', 'label' => 'SEO Audit', 'route' => 'admin.seo.audit'],
    ];
@endphp
<nav class="jp-tabs jp-tabs--wrap mb-3" aria-label="SEO Management sections">
    @foreach ($tabs as $tab)
        <a href="{{ route($tab['route']) }}"
           class="jp-tab @if(request()->routeIs($tab['route'].'*')) is-active @endif">
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
