@extends(client_layout('dashboard', 'admin'))

@section('title', 'SEO Audit')

@section('page-header')
    <div class="jp-between"><div class="col"><div class="page-pretitle"><a href="{{ route('admin.seo.overview') }}">SEO Management</a></div><h1 class="jp-page-title">SEO Audit</h1></div></div>
@endsection

@section('content')
    @include('dashboard.admin.seo.partials.nav', ['seoNav' => 'audit'])
    <div class="jp-card">
        <div class="table-responsive"><table class="jp-table"><thead><tr><th>Severity</th><th>Page</th><th>Path</th><th>Finding</th><th></th></tr></thead><tbody>
            @foreach ($findings as $finding)
                <tr>
                    <td><span class="badge bg-{{ match($finding['severity']) { 'error' => 'danger', 'warning' => 'warning', 'info' => 'info', default => 'success' } }}-lt">{{ ucfirst($finding['severity']) }}</span></td>
                    <td>{{ $finding['label'] ?? '—' }}</td>
                    <td><code>{{ $finding['path'] ?? '—' }}</code></td>
                    <td>{{ $finding['message'] }}</td>
                    <td>
                        @if (!empty($finding['page_ref']))
                            <a href="{{ route('admin.seo.pages.edit', ['sourceType' => explode(':', $finding['page_ref'])[0], 'sourceId' => explode(':', $finding['page_ref'])[1] ?? '']) }}" class="jp-btn jp-btn--ghost btn-sm">Fix</a>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody></table></div>
    </div>
@endsection
