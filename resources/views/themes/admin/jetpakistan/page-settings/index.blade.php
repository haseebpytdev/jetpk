@extends(client_layout('dashboard', 'admin'))

@section('title', 'Page settings')

@section('page-header')
    <div class="jp-between">
        <div>
            <h1>Page settings</h1>
            <p>Client-scoped public page content with draft, preview, and publish.</p>
        </div>
        <a href="{{ client_route('admin.page-settings.palette') }}" class="jp-btn jp-btn--sm jp-btn--ghost">Theme palette</a>
    </div>
@endsection

@section('content')
    @if (! empty($migrationRequired))
        <div class="jp-alert jp-alert--warn">Page builder tables are not migrated yet. Run <code>php artisan migrate</code> on the server.</div>
    @endif

    @if (session('status'))
        <div class="jp-alert jp-alert--info">{{ session('status') }}</div>
    @endif

    <div class="jp-dtable-wrap">
        <table class="jp-dtable">
            <thead>
                <tr>
                    <th>Page</th>
                    <th>Draft</th>
                    <th>Published</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pages as $page)
                    <tr>
                        <td data-label="Page">{{ $page['label'] }}</td>
                        <td data-label="Draft">{{ $page['has_draft'] ? 'Yes' : '—' }}</td>
                        <td data-label="Published">{{ $page['has_published'] ? ($page['published_at'] ?? 'Yes') : '—' }}</td>
                        <td data-label="Actions">
                            <a href="{{ client_route('admin.page-settings.edit', ['pageKey' => $page['key']]) }}" class="jp-btn jp-btn--sm">Edit</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
