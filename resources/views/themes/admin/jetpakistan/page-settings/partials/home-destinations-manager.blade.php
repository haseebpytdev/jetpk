@php
    $destItems = collect(data_get($content, 'destinations.items', []))->values();
    if ($destItems->count() < 4) {
        $destItems = $destItems->pad(4, []);
    }
@endphp
<div class="jp-card jp-page-section jp-is-hidden" id="section-destinations" data-jp-section-panel="destinations">
    <div class="jp-between jp-section-toggle">
        <h2 class="jp-card__title" style="margin:0;">Popular destinations</h2>
        <div class="jp-toggle">
            <input type="hidden" name="content[destinations][enabled]" value="0">
            <input type="checkbox" id="destinations-enabled" name="content[destinations][enabled]" value="1" @checked(data_get($content, 'destinations.enabled', '1') == '1')>
            <label for="destinations-enabled">Enabled</label>
        </div>
    </div>
    <div class="jp-grid jp-grid--2">
        <div class="jp-field">
            <label class="jp-field__label" for="dest-eyebrow">Eyebrow</label>
            <input id="dest-eyebrow" class="jp-control" name="content[destinations][eyebrow]" value="{{ data_get($content, 'destinations.eyebrow') }}">
        </div>
        <div class="jp-field">
            <label class="jp-field__label" for="dest-title">Heading</label>
            <input id="dest-title" class="jp-control" name="content[destinations][title]" value="{{ data_get($content, 'destinations.title') }}">
        </div>
    </div>
    <div class="jp-field">
        <label class="jp-field__label" for="dest-subtitle">Subtitle</label>
        <textarea id="dest-subtitle" class="jp-control jp-control--textarea" rows="2" name="content[destinations][subtitle]">{{ data_get($content, 'destinations.subtitle') }}</textarea>
    </div>
    <div class="jp-grid jp-grid--2">
        <div class="jp-field">
            <label class="jp-field__label" for="dest-cta-text">Section CTA label</label>
            <input id="dest-cta-text" class="jp-control" name="content[destinations][cta_text]" value="{{ data_get($content, 'destinations.cta_text') }}">
        </div>
        <div class="jp-field">
            <label class="jp-field__label" for="dest-cta-url">Section CTA URL</label>
            <input id="dest-cta-url" class="jp-control" name="content[destinations][cta_url]" value="{{ data_get($content, 'destinations.cta_url') }}">
        </div>
    </div>

    <div class="jp-repeatable-list" data-jp-repeatable="destinations" data-jp-repeatable-max="{{ (int) config('jetpk_homepage.max_destinations', 12) }}">
        @foreach ($destItems as $i => $item)
            @php
                $item = is_array($item) ? $item : [];
                $destId = data_get($item, 'id') ?: 'dest-'.$i;
                $assetKey = data_get($item, 'image_asset_key') ?: 'destination_'.$destId;
                $existingAsset = $assets->firstWhere('asset_key', $assetKey) ?? $assets->firstWhere('asset_key', 'destination_'.($i + 1));
            @endphp
            <div class="jp-repeatable-card" data-jp-repeatable-row>
                <div class="jp-between">
                    <p class="jp-muted" style="margin:0;">Destination {{ $i + 1 }}</p>
                    <div class="jp-toggle">
                        <input type="hidden" name="content[destinations][items][{{ $i }}][enabled]" value="0">
                        <input type="checkbox" id="dest-enabled-{{ $i }}" name="content[destinations][items][{{ $i }}][enabled]" value="1" @checked(data_get($item, 'enabled', '1') == '1')>
                        <label for="dest-enabled-{{ $i }}">Active</label>
                    </div>
                </div>
                <input type="hidden" name="content[destinations][items][{{ $i }}][id]" value="{{ $destId }}">
                <input type="hidden" name="content[destinations][items][{{ $i }}][sort_order]" value="{{ data_get($item, 'sort_order', $i) }}">
                <input type="hidden" name="content[destinations][items][{{ $i }}][image_asset_key]" value="{{ $assetKey }}">
                <div class="jp-grid jp-grid--2">
                    <div class="jp-field">
                        <label class="jp-field__label">City / title</label>
                        <input class="jp-control" name="content[destinations][items][{{ $i }}][title]" value="{{ data_get($item, 'title') }}">
                    </div>
                    <div class="jp-field">
                        <label class="jp-field__label">IATA code</label>
                        <input class="jp-control" name="content[destinations][items][{{ $i }}][code]" value="{{ data_get($item, 'code') }}" maxlength="3">
                    </div>
                    <div class="jp-field">
                        <label class="jp-field__label">Country label</label>
                        <input class="jp-control" name="content[destinations][items][{{ $i }}][country]" value="{{ data_get($item, 'country') }}">
                    </div>
                    <div class="jp-field">
                        <label class="jp-field__label">Manual price (PKR)</label>
                        <input class="jp-control" name="content[destinations][items][{{ $i }}][manual_fallback_price]" value="{{ data_get($item, 'manual_fallback_price', data_get($item, 'price')) }}">
                    </div>
                    <div class="jp-field">
                        <label class="jp-field__label">Card link URL</label>
                        <input class="jp-control" name="content[destinations][items][{{ $i }}][link]" value="{{ data_get($item, 'link') }}">
                    </div>
                    <div class="jp-field">
                        <label class="jp-field__label">Badge</label>
                        <input class="jp-control" name="content[destinations][items][{{ $i }}][badge]" value="{{ data_get($item, 'badge') }}">
                    </div>
                </div>
                <div class="jp-field">
                    <label class="jp-field__label">Description</label>
                    <textarea class="jp-control jp-control--textarea" rows="2" name="content[destinations][items][{{ $i }}][text]">{{ data_get($item, 'text') }}</textarea>
                </div>
                <div class="jp-field">
                    <label class="jp-field__label">Image alt text</label>
                    <input class="jp-control" name="content[destinations][items][{{ $i }}][alt]" value="{{ data_get($item, 'alt') }}">
                </div>
                <div class="jp-media-inline">
                    @if ($existingAsset?->public_url)
                        <img src="{{ $existingAsset->public_url }}" alt="" class="jp-media-inline__preview" loading="lazy">
                    @endif
                    <div class="jp-field">
                        <label class="jp-field__label">Upload image (JPEG/PNG/WebP, max 5 MB)</label>
                        <input type="file" name="destination_files[{{ $destId }}]" accept="image/jpeg,image/png,image/webp" class="jp-control">
                    </div>
                    @if ($existingAsset)
                        <label class="jp-toggle">
                            <input type="checkbox" name="destination_remove[{{ $destId }}]" value="1">
                            Remove current image on save
                        </label>
                    @endif
                </div>
                <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost" data-jp-repeatable-remove>Remove destination</button>
            </div>
        @endforeach
    </div>
    <button type="button" class="jp-btn jp-btn--sm" data-jp-repeatable-add="destinations">Add destination</button>
</div>
