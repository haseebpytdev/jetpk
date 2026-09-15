@extends(client_layout('dashboard', 'admin'))

@section('title', 'Sitemap & Indexing')

@section('page-header')
    <div class="jp-between"><div class="col"><div class="page-pretitle"><a href="{{ route('admin.seo.overview') }}">SEO Management</a></div><h1 class="jp-page-title">Sitemap & Indexing</h1></div></div>
@endsection

@section('content')
    @include('dashboard.admin.seo.partials.nav', ['seoNav' => 'sitemap'])
    <div class="jp-card mb-3">
        <dl class="row mb-0">
            <dt class="col-sm-3">Sitemap URL</dt><dd class="col-sm-9"><a href="{{ $meta['sitemap_url'] }}" target="_blank" rel="noopener">{{ $meta['sitemap_url'] }}</a></dd>
            <dt class="col-sm-3">Route count</dt><dd class="col-sm-9">{{ $meta['route_count'] }}</dd>
            <dt class="col-sm-3">Laravel API</dt><dd class="col-sm-9"><code>{{ $meta['api_route'] }}</code></dd>
        </dl>
    </div>
    <div class="jp-card">
        <div class="table-responsive"><table class="jp-table"><thead><tr><th>Page</th><th>Path</th><th>Source</th><th>Indexable</th><th>In sitemap</th><th>Reason</th></tr></thead><tbody>
            @foreach ($entries as $entry)
                <tr>
                    <td>{{ $entry['label'] }}</td>
                    <td><code>{{ $entry['path'] }}</code></td>
                    <td>{{ ucfirst($entry['source_type']) }}</td>
                    <td>{{ ($entry['indexable'] ?? false) ? 'Yes' : 'No' }}</td>
                    <td>{{ ($entry['included'] ?? false) ? 'Yes' : 'No' }}</td>
                    <td class="small text-muted">{{ $entry['excluded_reason'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody></table></div>
    </div>
@endsection
