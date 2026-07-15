@extends(client_layout('dashboard', 'admin'))

@section('title', 'Homepage tiles')

@section('page-header')
    <div class="jp-between">
        <div>
            <p class="jp-cell-sub"><a href="{{ client_route('admin.group-ticketing.index') }}">Group Ticketing</a></p>
            <h1>Homepage tiles</h1>
            <p>Customize titles and images for group category tiles.</p>
        </div>
    </div>
@endsection

@section('content')
@include('themes.admin.jetpakistan.partials.flash')

<div class="jp-alert jp-alert--info">
    Tiles are generated from live group inventory. Upload images or customize titles here, then save all tiles at once.
</div>

@if ($tiles->isEmpty())
    <div class="jp-card">
        <x-themes.admin.jetpakistan.components.empty-state title="No inventory synced" message="Run a sync from the Inventory page." />
    </div>
@else
    <form method="POST" action="{{ client_route('admin.group-ticketing.tiles.batch-upsert') }}" enctype="multipart/form-data">
        @csrf
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;">
            @foreach ($tiles as $tile)
                @php
                    $formKey = $tile['target_type']->value === 'category'
                        ? 'category:'.$tile['target_value']
                        : 'all';
                @endphp
                <div class="jp-card @if($tile['is_hidden']) is-muted @endif">
                    @if ($tile['image_url'])
                        <img src="{{ $tile['image_url'] }}" alt="" style="width: 100%; max-height: 120px; object-fit: cover; border-radius: 8px; margin-bottom: 12px;">
                    @endif
                    <strong>{{ $tile['display_title'] }}</strong>
                    <p class="jp-cell-sub">
                        {{ $tile['package_count'] }} package(s)
                        @if ($tile['target_type']->value === 'category')
                            · <code>{{ $tile['target_value'] }}</code>
                        @else
                            · Virtual · <code>/groups/search</code>
                        @endif
                        @if ($tile['is_hidden'])
                            · Hidden on homepage
                        @endif
                    </p>

                    <input type="hidden" name="tiles[{{ $formKey }}][target_type]" value="{{ $tile['target_type']->value }}">
                    @if ($tile['target_value'])
                        <input type="hidden" name="tiles[{{ $formKey }}][target_value]" value="{{ $tile['target_value'] }}">
                    @endif

                    <label class="jp-label" for="tile-title-{{ $formKey }}">Title override</label>
                    <input type="text" id="tile-title-{{ $formKey }}" name="tiles[{{ $formKey }}][title]" class="jp-input @error('tiles.'.$formKey.'.title') is-invalid @enderror" value="{{ old('tiles.'.$formKey.'.title', $tile['title_override'] ?? '') }}" placeholder="{{ $tile['default_title'] }}" maxlength="120" style="margin-bottom: 8px;">
                    @error('tiles.'.$formKey.'.title')<div class="jp-cell-sub" style="color: var(--warn);">{{ $message }}</div>@enderror

                    <label class="jp-label" for="tile-image-{{ $formKey }}">Image</label>
                    <input type="file" id="tile-image-{{ $formKey }}" name="tiles[{{ $formKey }}][image]" class="jp-input @error('tiles.'.$formKey.'.image') is-invalid @enderror" accept="image/jpeg,image/png,image/webp" style="margin-bottom: 8px;">

                    <label class="jp-label" for="tile-order-{{ $formKey }}">Order</label>
                    <input type="number" id="tile-order-{{ $formKey }}" name="tiles[{{ $formKey }}][sort_order]" class="jp-input @error('tiles.'.$formKey.'.sort_order') is-invalid @enderror" value="{{ old('tiles.'.$formKey.'.sort_order', $tile['sort_order']) }}" min="0" style="margin-bottom: 8px;">

                    <label style="display: inline-flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                        <input type="hidden" name="tiles[{{ $formKey }}][is_active]" value="0">
                        <input type="checkbox" name="tiles[{{ $formKey }}][is_active]" value="1" @checked(old('tiles.'.$formKey.'.is_active', $tile['is_active_override'] ? '1' : '0') == '1')>
                        <span>Show on homepage</span>
                    </label>

                    @if ($tile['override_id'])
                        <button type="submit" form="reset-tile-{{ $tile['override_id'] }}" class="jp-btn jp-btn--sm jp-btn--ghost" style="width: 100%;" onclick="return confirm('Reset this tile to defaults?')">Reset to defaults</button>
                    @endif
                </div>
            @endforeach
        </div>
        <div style="margin-top: 16px;">
            <button type="submit" class="jp-btn">Save all homepage tiles</button>
        </div>
    </form>

    @foreach ($tiles as $tile)
        @if ($tile['override_id'])
            <form id="reset-tile-{{ $tile['override_id'] }}" method="POST" action="{{ client_route('admin.group-ticketing.tiles.destroy', $tile['override_id']) }}" hidden>
                @csrf
                @method('DELETE')
            </form>
        @endif
    @endforeach
@endif
@endsection
