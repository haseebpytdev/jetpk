@extends(client_layout('dashboard', 'admin'))

@section('title', 'Page SEO')

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle"><a href="{{ route('admin.seo.overview') }}">SEO Management</a></div>
            <h1 class="jp-page-title">Page SEO</h1>
        </div>
    </div>
@endsection

@section('content')
    @include('dashboard.admin.seo.partials.nav', ['seoNav' => 'pages'])

    <div class="jp-card">
        <div class="table-responsive">
            <table class="jp-table">
                <thead>
                    <tr>
                        <th>Page</th>
                        <th>Path</th>
                        <th>Source</th>
                        <th>Publish</th>
                        <th>Draft</th>
                        <th>Health</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pages as $page)
                        <tr>
                            <td class="fw-semibold">{{ $page['label'] }}</td>
                            <td><code>{{ $page['path'] }}</code></td>
                            <td>{{ ucfirst($page['source_type']) }}</td>
                            <td>{{ ($page['published'] ?? false) ? 'Published' : 'Fallback' }}</td>
                            <td>{{ ($page['has_draft'] ?? false) ? 'Draft pending' : '—' }}</td>
                            <td>{{ ucfirst($page['health_status']) }}</td>
                            <td><a href="{{ route('admin.seo.pages.edit', ['sourceType' => $page['source_type'], 'sourceId' => $page['source_id']]) }}" class="jp-btn jp-btn--primary btn-sm">Manage</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
