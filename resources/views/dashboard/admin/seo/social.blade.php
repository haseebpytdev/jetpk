@extends(client_layout('dashboard', 'admin'))

@section('title', 'Social & Open Graph')

@section('page-header')
    <div class="jp-between"><div class="col"><div class="page-pretitle"><a href="{{ route('admin.seo.overview') }}">SEO Management</a></div><h1 class="jp-page-title">Social & Open Graph</h1></div></div>
@endsection

@section('content')
    @include('dashboard.admin.seo.partials.nav', ['seoNav' => 'social'])
    @if (session('status'))<div class="jp-alert jp-alert--success">{{ session('status') }}</div>@endif
    <div class="jp-alert jp-alert--info small">Page-level OG overrides beat these global defaults on public pages.</div>
    <form method="POST" action="{{ route('admin.seo.social.update') }}" class="jp-card">
        @csrf @method('PATCH')
        <label class="jp-label">Default OG title</label><input class="jp-control" name="og_title" value="{{ old('og_title', $global['og_title']) }}">
        <label class="jp-label mt-2">Default OG description</label><textarea class="jp-control" rows="2" name="og_description">{{ old('og_description', $global['og_description']) }}</textarea>
        <label class="jp-label mt-2">Default OG image</label><input class="jp-control" name="og_image" value="{{ old('og_image', $global['og_image']) }}">
        <button type="submit" class="jp-btn jp-btn--primary mt-3">Save draft</button>
    </form>
    <form method="POST" action="{{ route('admin.seo.global.publish') }}" class="mt-2">
        @csrf
        <button type="submit" class="jp-btn jp-btn--outline">Publish social defaults</button>
    </form>
    <div class="jp-card mt-3">
        <h2 class="jp-card__title">Pages with OG overrides</h2>
        <div class="table-responsive"><table class="jp-table"><thead><tr><th>Page</th><th>OG title</th><th></th></tr></thead><tbody>
            @forelse ($social['pages'] as $page)
                <tr><td>{{ $page['label'] }}</td><td>{{ $page['og_title'] }}</td><td><a href="{{ route('admin.seo.pages.edit', ['sourceType' => $page['source_type'], 'sourceId' => $page['source_id']]) }}">Edit</a></td></tr>
            @empty
                <tr><td colspan="3" class="text-muted">No page-level OG overrides yet.</td></tr>
            @endforelse
        </tbody></table></div>
    </div>
@endsection
