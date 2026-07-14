@extends(client_layout('dashboard', 'admin'))

@section('title', 'Media Library')

@section('page-header')
    <div class="jp-between">
        <div>
            <h1>Media Library</h1>
            <p>Reusable general-purpose media. Page-specific hero images belong in Page Settings; logo and favicon in Branding.</p>
        </div>
    </div>
@endsection

@section('content')
    @include('themes.admin.jetpakistan.partials.flash')

    <div class="jp-card jp-media-library-upload">
        <h2 class="jp-card__title">Upload media</h2>
        <p class="jp-help">General assets for reuse across the agency. Do not upload page-builder hero images here.</p>
        <form method="post" action="{{ route('admin.settings.media.store') }}" enctype="multipart/form-data" class="jp-form-shell">
            @csrf
            <div class="jp-form-grid jp-form-grid--2">
                <div class="jp-field jp-field--full">
                    <label class="jp-label" for="media-lib-file">File</label>
                    <div class="jp-file-control">
                        <input id="media-lib-file" type="file" name="file" class="jp-file-control__input" required>
                        <label for="media-lib-file" class="jp-file-control__btn">Choose file</label>
                        <span class="jp-file-control__name">No file chosen</span>
                    </div>
                </div>
                <div class="jp-field">
                    <label class="jp-label" for="media-collection">Collection</label>
                    <select id="media-collection" name="collection" class="jp-control">
                        <option value="general">General</option>
                        <option value="branding">Branding</option>
                        <option value="homepage">Homepage</option>
                    </select>
                </div>
                <div class="jp-field">
                    <label class="jp-label" for="media-alt">Alt text</label>
                    <input id="media-alt" class="jp-control" name="alt_text" maxlength="255">
                </div>
            </div>
            <div class="jp-action-bar">
                <button type="submit" class="jp-btn">Upload</button>
            </div>
        </form>
    </div>

    <div class="jp-card">
        <h2 class="jp-card__title">Library</h2>
        <form method="get" class="jp-form-grid jp-form-grid--4" style="margin-bottom:16px;">
            <div class="jp-field">
                <label class="jp-label" for="media-q">Search</label>
                <input id="media-q" class="jp-control" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Filename, alt, collection">
            </div>
            <div class="jp-field">
                <label class="jp-label" for="media-filter-collection">Collection</label>
                <select id="media-filter-collection" name="collection" class="jp-control">
                    <option value="">All</option>
                    @foreach ($collections ?? [] as $collection)
                        <option value="{{ strtolower($collection) }}" @selected(($filters['collection'] ?? '') === strtolower($collection))>{{ $collection }}</option>
                    @endforeach
                </select>
            </div>
            <div class="jp-field">
                <label class="jp-label" for="media-filter-type">Type</label>
                <select id="media-filter-type" name="type" class="jp-control">
                    <option value="">All</option>
                    <option value="image" @selected(($filters['type'] ?? '') === 'image')>Images</option>
                </select>
            </div>
            <div class="jp-field">
                <label class="jp-label" for="media-sort">Sort</label>
                <select id="media-sort" name="sort" class="jp-control">
                    <option value="newest" @selected(($filters['sort'] ?? '') === 'newest')>Newest</option>
                    <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Oldest</option>
                    <option value="name" @selected(($filters['sort'] ?? '') === 'name')>Name</option>
                    <option value="size" @selected(($filters['sort'] ?? '') === 'size')>Size</option>
                </select>
            </div>
            <div class="jp-action-bar" style="position:static;padding:0;">
                <button type="submit" class="jp-btn jp-btn--sm jp-btn--primary">Apply</button>
            </div>
        </form>
        @if ($mediaItems->isEmpty())
            <div class="jp-empty-state">
                <p>No media uploaded yet.</p>
                <p class="jp-muted">Upload reusable images for campaigns, emails, or internal reference.</p>
            </div>
        @else
            <div class="jp-media-library-grid">
                @foreach ($mediaItems as $media)
                    <article class="jp-media-library-card">
                        <div class="jp-media-library-card__thumb">
                            <img src="{{ asset('storage/'.$media->file_path) }}" alt="{{ $media->alt_text ?? $media->file_name }}" loading="lazy">
                        </div>
                        <div class="jp-media-library-card__body">
                            <h3 class="jp-media-library-card__title">{{ $media->file_name }}</h3>
                            <p class="jp-help">{{ $media->collection }} · {{ $media->mime_type }} · {{ number_format((int) $media->size_bytes / 1024, 1) }} KB</p>
                            <p class="jp-help">Owner: {{ $media->uploader?->name ?? 'Unknown' }}</p>
                            <p class="jp-help"><code>{{ $media->file_path }}</code></p>
                            <div class="jp-media-library-card__actions">
                                <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost" onclick="navigator.clipboard.writeText('{{ asset('storage/'.$media->file_path) }}')">Copy URL</button>
                                <form method="post" action="{{ route('admin.settings.media.destroy', $media) }}" onsubmit="return confirm('Delete this media item?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="jp-btn jp-btn--sm jp-btn--ghost">Delete</button>
                                </form>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="jp-pagination-wrap">{{ $mediaItems->links() }}</div>
        @endif
    </div>
@endsection
