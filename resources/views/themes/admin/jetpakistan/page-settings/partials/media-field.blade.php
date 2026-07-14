@php
    /** @var array<string, mixed> $field */
    /** @var \Illuminate\Support\Collection<int, \App\Models\ClientPageAsset> $assets */
    $assetKey = $field['key'];
    $existing = $assets->firstWhere('asset_key', $assetKey);
    $ratio = $field['ratio'] ?? '16:9';
    $maxKb = (int) ($field['max_kb'] ?? 5120);
    $accept = $field['accept'] ?? 'image/jpeg,image/png,image/webp';
    $usage = $field['usage'] ?? 'Used on the public page when published.';
    $previewUrl = $existing?->public_url;
    $fallback = $field['fallback'] ?? null;
    $statusLabel = $existing ? 'Uploaded' : 'Not uploaded';
    $filename = $existing?->meta_json['original_name'] ?? null;
@endphp
<div class="jp-media-field" data-media-field="{{ $assetKey }}">
    <div class="jp-media-field__preview" data-jp-media-preview>
        @if ($previewUrl)
            <img src="{{ $previewUrl }}" alt="{{ $existing?->alt_text ?? $field['label'] }}" loading="lazy" data-jp-media-preview-img>
        @elseif ($fallback)
            <img src="{{ asset($fallback) }}" alt="JetPK fallback" loading="lazy" class="jp-media-field__fallback">
        @else
            <span class="jp-muted">No image uploaded</span>
        @endif
    </div>
    <div class="jp-media-field__body">
        <div class="jp-media-field__head">
            <h3 class="jp-media-field__title">{{ $field['label'] }}</h3>
            <span class="jp-badge jp-badge--{{ $existing ? 'success' : 'muted' }}" data-jp-media-status>{{ $statusLabel }}</span>
        </div>
        <p class="jp-help">{{ $usage }}</p>
        <p class="jp-help">Key: <code>{{ $assetKey }}</code> · {{ $ratio }} · Max {{ number_format($maxKb) }} KB</p>
        @if ($filename)
            <p class="jp-help" data-jp-media-filename>File: {{ $filename }}</p>
        @endif

        <form method="post" action="{{ client_route('admin.page-settings.assets.store', ['pageKey' => $pageKey]) }}" enctype="multipart/form-data" class="jp-media-field__form" data-jp-media-form>
            @csrf
            <input type="hidden" name="asset_key" value="{{ $assetKey }}">
            <div class="jp-field">
                <label class="jp-label" for="media-file-{{ $assetKey }}">Image file</label>
                <div class="jp-file-control">
                    <input id="media-file-{{ $assetKey }}" type="file" name="file" class="jp-file-control__input" accept="{{ $accept }}" required data-jp-file-input>
                    <label for="media-file-{{ $assetKey }}" class="jp-file-control__btn">Choose image</label>
                    <span class="jp-file-control__name" data-jp-file-name>No file chosen</span>
                </div>
            </div>
            <div class="jp-field">
                <label class="jp-label" for="media-alt-{{ $assetKey }}">Alt text</label>
                <input id="media-alt-{{ $assetKey }}" type="text" name="alt_text" class="jp-control" value="{{ $existing?->alt_text }}" maxlength="255" placeholder="Describe the image for accessibility">
            </div>
            <div class="jp-media-field__actions">
                <button type="submit" class="jp-btn jp-btn--sm">Upload replacement</button>
                @if (Route::has('admin.settings.media.index'))
                    <a href="{{ client_route('admin.settings.media.index') }}" class="jp-btn jp-btn--sm jp-btn--ghost" target="_blank" rel="noopener">Choose from Media Library</a>
                @endif
            </div>
        </form>

        @if ($existing)
            <form method="post" action="{{ client_route('admin.page-settings.assets.destroy', ['pageKey' => $pageKey, 'asset' => $existing->id]) }}" class="jp-media-field__remove" onsubmit="return confirm('Remove this image?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="jp-btn jp-btn--sm jp-btn--ghost">Remove image</button>
            </form>
        @endif
    </div>
</div>
