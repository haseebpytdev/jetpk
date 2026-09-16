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
@push('styles')
<style>
@media (max-width: 640px) {
  nav.jp-tabs.jp-tabs--wrap { flex-wrap: wrap; row-gap: 0.375rem; max-width: 100%; }
  .ota-dashboard-main .jp-stat-grid,
  nav.jp-tabs ~ .jp-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); min-width: 0; max-width: 100%; }
  .ota-dashboard-main .jp-card:has(.ota-r-table-wrap),
  nav.jp-tabs ~ .jp-card:has(.ota-r-table-wrap) { overflow: hidden; max-width: 100%; }
  nav.jp-tabs ~ .jp-card .table-responsive,
  nav.jp-tabs ~ .jp-card .ota-r-table-wrap,
  .ota-dashboard-main .ota-r-table-wrap { overflow-x: auto; max-width: 100%; -webkit-overflow-scrolling: touch; }
  .ota-dashboard-main .jp-page-title { overflow-wrap: anywhere; }
}
</style>
@endpush
<nav class="jp-tabs jp-tabs--wrap mb-3" aria-label="SEO Management sections">
    @foreach ($tabs as $tab)
        <a href="{{ route($tab['route']) }}"
           class="jp-tab @if(request()->routeIs($tab['route'].'*')) is-active @endif">
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
