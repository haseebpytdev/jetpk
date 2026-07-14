@extends(client_layout('dashboard', 'admin'))

@section('title', 'CMS pages')

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle"><a href="{{ route('admin.settings.index') }}">Settings</a></div>
            <h1 class="jp-page-title">CMS pages</h1>
        </div>
        <div class="col-auto ms-auto d-flex gap-2">
            <a href="{{ route('admin.settings.index') }}" class="jp-btn jp-btn--ghost btn-sm">Settings hub</a>
            <a href="{{ route('admin.cms-pages.create') }}" class="jp-btn jp-btn--primary btn-sm">
                <i class="ti ti-plus me-1"></i>Create page
            </a>
        </div>
    </div>
@endsection

@section('content')
    @if (session('status') === 'cms-page-created')
        <div class="jp-alert jp-alert--success">CMS page created.</div>
    @elseif (session('status') === 'cms-page-updated')
        <div class="jp-alert jp-alert--success">CMS page updated.</div>
    @elseif (session('status') === 'cms-page-archived')
        <div class="jp-alert jp-alert--success">CMS page archived.</div>
    @elseif (session('status') === 'cms-page-deleted')
        <div class="jp-alert jp-alert--success">CMS page deleted.</div>
    @endif

    <div class="jp-alert jp-alert--info small mb-3">
        Static pages are published at <code>/pages/{slug}</code>. Footer menu integration is not wired yet — footer fields are stored for a future sprint.
    </div>

    <form method="GET" class="jp-card">
        <div class="jp-card__body">
            <div class="jp-form-grid jp-form-grid--filter">
                <div class="col-md-4">
                    <label class="jp-label">Search</label>
                    <input type="text" name="q" class="jp-control" value="{{ $filters['q'] ?? '' }}" placeholder="Title or slug">
                </div>
                <div class="col-md-3">
                    <label class="jp-label">Status</label>
                    <select name="status" class="jp-control">
                        <option value="">All</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="jp-btn jp-btn--outline w-100">Filter</button>
                </div>
            </div>
        </div>
    </form>

    <div class="jp-card">
        <div class="table-responsive">
            <table class="jp-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Slug</th>
                        <th>Status</th>
                        <th>Footer group</th>
                        <th>Show in footer</th>
                        <th>Sort</th>
                        <th>Updated</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pages as $page)
                        <tr>
                            <td class="fw-semibold">{{ $page->title }}</td>
                            <td><code>{{ $page->slug }}</code></td>
                            <td>
                                @if ($page->status === 'active')
                                    <span class="badge bg-success-lt">Active</span>
                                @elseif ($page->status === 'archived')
                                    <span class="badge bg-secondary-lt">Archived</span>
                                @else
                                    <span class="badge bg-warning-lt">Draft</span>
                                @endif
                            </td>
                            <td>{{ $page->footer_group ? str_replace('_', ' ', ucfirst($page->footer_group)) : '—' }}</td>
                            <td>{{ $page->show_in_footer ? 'Yes' : 'No' }}</td>
                            <td>{{ $page->footer_sort_order }}</td>
                            <td class="text-secondary">{{ $page->updated_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.cms-pages.edit', $page) }}" class="jp-btn jp-btn--sm jp-btn--outline">Edit</a>
                                <a href="{{ route('admin.cms-pages.preview', $page) }}" class="jp-btn jp-btn--sm jp-btn--ghost" target="_blank" rel="noopener">Preview</a>
                                @if ($page->status !== 'archived')
                                    <form method="POST" action="{{ route('admin.cms-pages.archive', $page) }}" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-outline-warning" onclick="return confirm('Archive this page? It will no longer be public.')">Archive</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.cms-pages.destroy', $page) }}" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this page?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-secondary py-4">No CMS pages yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($pages->hasPages())
            <div class="card-footer">{{ $pages->links() }}</div>
        @endif
    </div>
@endsection
