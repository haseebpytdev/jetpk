@extends(client_layout('dashboard', 'admin'))

@section('title', 'SEO Management')

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle">Administration</div>
            <h1 class="jp-page-title">SEO Management</h1>
        </div>
    </div>
@endsection

@section('content')
    @include('dashboard.admin.seo.partials.nav', ['seoNav' => 'overview'])

    @if (session('status'))
        <div class="jp-alert jp-alert--success">{{ session('status') }}</div>
    @endif

    <div class="jp-stat-grid mb-3">
        @foreach ([
            'Total pages' => $stats['total'],
            'Indexable' => $stats['indexable'],
            'Noindex' => $stats['noindex'],
            'Missing titles' => $stats['missing_titles'],
            'Missing descriptions' => $stats['missing_descriptions'],
            'Missing canonical' => $stats['missing_canonical'],
            'Missing OG image' => $stats['missing_og_image'],
            'In sitemap' => $stats['sitemap_count'],
            'Needs attention' => $stats['attention'],
        ] as $label => $value)
            <div class="jp-card jp-card--stat">
                <div class="jp-card__body">
                    <div class="jp-stat__label">{{ $label }}</div>
                    <div class="jp-stat__value">{{ $value }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="jp-card">
        <div class="jp-card__head"><h2 class="jp-card__title mb-0">Live SEO pages</h2></div>
        <div class="table-responsive ota-r-table-wrap">
            <table class="jp-table">
                <thead>
                    <tr>
                        <th>Page</th>
                        <th>URL</th>
                        <th>Source</th>
                        <th>Title</th>
                        <th>Indexing</th>
                        <th>Sitemap</th>
                        <th>Health</th>
                        <th>Updated</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pages as $page)
                        <tr>
                            <td class="fw-semibold">{{ $page['label'] }}</td>
                            <td><code>{{ $page['path'] }}</code></td>
                            <td>{{ ucfirst($page['source_type']) }}</td>
                            <td class="small">{{ \Illuminate\Support\Str::limit($page['title'], 48) }}</td>
                            <td>{{ ($page['index'] ?? false) ? 'Index' : 'Noindex' }}</td>
                            <td>{{ ($page['sitemap_included'] ?? false) ? 'Included' : 'Excluded' }}</td>
                            <td><span class="badge bg-{{ match($page['health_status']) { 'error' => 'danger', 'warning' => 'warning', 'info' => 'info', default => 'success' } }}-lt">{{ ucfirst($page['health_status']) }}</span></td>
                            <td class="small text-muted">{{ $page['updated_at'] ? \Illuminate\Support\Carbon::parse($page['updated_at'])->diffForHumans() : '—' }}</td>
                            <td><a href="{{ route('admin.seo.pages.edit', ['sourceType' => $page['source_type'], 'sourceId' => $page['source_id']]) }}" class="jp-btn jp-btn--ghost btn-sm">Edit</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
