@extends(client_layout('dashboard', 'admin'))

@section('title', $tile->exists ? 'Edit tile' : 'Add tile')

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle"><a href="{{ route('admin.group-ticketing.tiles.index') }}">Homepage tiles</a></div>
            <h1 class="jp-page-title">{{ $tile->exists ? 'Edit tile' : 'Add tile' }}</h1>
        </div>
    </div>
@endsection

@section('content')
    <form method="POST" action="{{ $action }}" class="jp-card" enctype="multipart/form-data">
        @csrf
        @if ($method !== 'POST')
            @method($method)
        @endif
        <div class="jp-card__body">
            <div class="mb-3">
                <label class="jp-label">Title</label>
                <input type="text" name="title" class="jp-control" value="{{ old('title', $tile->title) }}" required maxlength="120">
            </div>
            <div class="mb-3">
                <label class="jp-label">Tile image</label>
                @if ($tile->imageUrl())
                    <div class="mb-2">
                        <img src="{{ $tile->imageUrl() }}" alt="" class="rounded border" style="max-height: 120px; max-width: 220px; object-fit: cover;">
                    </div>
                @endif
                <input type="file" name="image" class="jp-control" accept="image/jpeg,image/png,image/webp">
                <div class="form-text">JPG, PNG, or WebP. Max 2 MB. Leave empty to keep the current image.</div>
            </div>
            <div class="mb-3">
                <label class="jp-label">Target type</label>
                <select name="target_type" class="jp-control" required>
                    @foreach ($targetTypes as $type)
                        <option value="{{ $type->value }}" @selected(old('target_type', $tile->target_type?->value) === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="jp-label">Target value (sector code or category slug)</label>
                <input type="text" name="target_value" class="jp-control" value="{{ old('target_value', $tile->target_value) }}" maxlength="120">
            </div>
            <div class="mb-3">
                <label class="jp-label">Sort order</label>
                <input type="number" name="sort_order" class="jp-control" value="{{ old('sort_order', $tile->sort_order ?? 0) }}" min="0">
                <div class="form-text">Lower numbers appear first among category tiles on the homepage.</div>
            </div>
            <div class="form-check mb-3">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" class="form-check-input" id="tile-active" @checked(old('is_active', $tile->is_active ?? true))>
                <label class="form-check-label" for="tile-active">Active</label>
            </div>
        </div>
        <div class="card-footer text-end">
            <a href="{{ route('admin.group-ticketing.tiles.index') }}" class="jp-btn jp-btn--ghost">Cancel</a>
            <button type="submit" class="jp-btn jp-btn--primary">Save</button>
        </div>
    </form>
@endsection
