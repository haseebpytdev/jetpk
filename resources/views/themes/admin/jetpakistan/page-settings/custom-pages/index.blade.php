@extends(client_layout('dashboard', 'admin'))

@section('title', 'Custom content pages')

@section('content')
<div class="jp-card">
    <div class="jp-between">
        <h1>Custom content pages</h1>
        <a href="{{ route('admin.page-settings.custom-pages.create') }}" class="jp-btn jp-btn--sm">Create page</a>
    </div>
    <table class="jp-table">
        <thead>
            <tr><th>Title</th><th>Slug</th><th>Enabled</th><th></th></tr>
        </thead>
        <tbody>
            @forelse ($pages as $page)
                <tr>
                    <td>{{ $page->public_title }}</td>
                    <td>/{{ $page->slug }}</td>
                    <td>{{ $page->enabled ? 'Yes' : 'No' }}</td>
                    <td><a href="{{ route('admin.page-settings.edit', ['pageKey' => $page->pageKey()]) }}">Edit</a></td>
                </tr>
            @empty
                <tr><td colspan="4">No custom pages yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
