@extends(client_layout('dashboard', 'admin'))

@section('title', 'Homepage tiles')

@section('page-header')
    <div class="jp-between ota-admin-page-header">
        <div class="col">
            <div class="page-pretitle"><a href="{{ route('admin.group-ticketing.index') }}">Group Ticketing</a></div>
            <h1 class="jp-page-title">Homepage tiles</h1>
        </div>
    </div>
@endsection

@section('content')
    @if (session('status') === 'tiles-saved')
        <div class="jp-alert jp-alert--success">All homepage tiles saved successfully.</div>
    @elseif (session('status') === 'tile-saved')
        <div class="jp-alert jp-alert--success">Tile saved successfully.</div>
    @elseif (session('status') === 'tile-reset')
        <div class="jp-alert jp-alert--success">Tile customization reset.</div>
    @endif

    <div class="jp-alert jp-alert--info mb-3">
        Tiles are generated from live group inventory. Upload images or customize titles here, then save all tiles at once.
    </div>

    @if ($tiles->isEmpty())
        <div class="jp-card">
            <div class="card-body text-secondary">No inventory synced yet. Run a sync from the Inventory page.</div>
        </div>
    @else
        <form method="POST" action="{{ route('admin.group-ticketing.tiles.batch-upsert') }}" enctype="multipart/form-data">
            @csrf

            <div class="ota-admin-group-tiles-grid">
                @foreach ($tiles as $tile)
                    @php
                        $formKey = $tile['target_type']->value === 'category'
                            ? 'category:'.$tile['target_value']
                            : 'all';
                    @endphp
                    <div class="ota-admin-group-tile-card @if($tile['is_hidden']) is-hidden @endif">
                        <div class="ota-admin-group-tile-card__preview">
                            @if ($tile['image_url'])
                                <img src="{{ $tile['image_url'] }}" alt="">
                            @else
                                <span class="ota-admin-group-tile-card__placeholder"><i class="ti ti-users"></i></span>
                            @endif
                        </div>
                        <div class="ota-admin-group-tile-card__body">
                            <div class="ota-admin-group-tile-card__title">{{ $tile['display_title'] }}</div>
                            <div class="ota-admin-group-tile-card__meta">
                                {{ $tile['package_count'] }} package(s)
                                @if ($tile['target_type']->value === 'category')
                                    · <code>{{ $tile['target_value'] }}</code>
                                @else
                                    · Virtual · <code>/groups/search</code>
                                @endif
                                @if ($tile['is_hidden'])
                                    · <span class="text-warning">Hidden on homepage</span>
                                @endif
                            </div>
                            @if ($tile['title_override'] && $tile['title_override'] !== $tile['default_title'])
                                <div class="small text-secondary mb-2">Default: {{ $tile['default_title'] }}</div>
                            @endif

                            <input type="hidden" name="tiles[{{ $formKey }}][target_type]" value="{{ $tile['target_type']->value }}">
                            @if ($tile['target_value'])
                                <input type="hidden" name="tiles[{{ $formKey }}][target_value]" value="{{ $tile['target_value'] }}">
                            @endif

                            <div class="vstack gap-2">
                                <div>
                                    <label class="jp-label small mb-1" for="tile-title-{{ $formKey }}">Title override</label>
                                    <input type="text" id="tile-title-{{ $formKey }}" name="tiles[{{ $formKey }}][title]" class="jp-control jp-control-sm @error('tiles.'.$formKey.'.title') is-invalid @enderror" value="{{ old('tiles.'.$formKey.'.title', $tile['title_override'] ?? '') }}" placeholder="{{ $tile['default_title'] }}" maxlength="120">
                                    @error('tiles.'.$formKey.'.title')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div>
                                    <label class="jp-label small mb-1" for="tile-image-{{ $formKey }}">Image</label>
                                    <input type="file" id="tile-image-{{ $formKey }}" name="tiles[{{ $formKey }}][image]" class="jp-control jp-control-sm @error('tiles.'.$formKey.'.image') is-invalid @enderror" accept="image/jpeg,image/png,image/webp">
                                    @if ($tile['image_url'])
                                        <div class="small text-secondary mt-1">Current image shown above.</div>
                                    @endif
                                    @error('tiles.'.$formKey.'.image')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="jp-label small mb-1" for="tile-order-{{ $formKey }}">Order</label>
                                        <input type="number" id="tile-order-{{ $formKey }}" name="tiles[{{ $formKey }}][sort_order]" class="jp-control jp-control-sm @error('tiles.'.$formKey.'.sort_order') is-invalid @enderror" value="{{ old('tiles.'.$formKey.'.sort_order', $tile['sort_order']) }}" min="0">
                                        @error('tiles.'.$formKey.'.sort_order')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-6 d-flex align-items-end">
                                        <label class="form-check mb-2">
                                            <input type="hidden" name="tiles[{{ $formKey }}][is_active]" value="0">
                                            <input type="checkbox" name="tiles[{{ $formKey }}][is_active]" value="1" class="form-check-input" @checked(old('tiles.'.$formKey.'.is_active', $tile['is_active_override'] ? '1' : '0') == '1')>
                                            <span class="form-check-label small">Show on homepage</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            @if ($tile['override_id'])
                                <button type="submit" form="reset-tile-{{ $tile['override_id'] }}" class="jp-btn jp-btn--ghost btn-sm w-100 mt-2" onclick="return confirm('Reset this tile to defaults?')">Reset to defaults</button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-3 ota-admin-action-group">
                <button type="submit" class="jp-btn jp-btn--primary">Save all homepage tiles</button>
            </div>
        </form>

        @foreach ($tiles as $tile)
            @if ($tile['override_id'])
                <form id="reset-tile-{{ $tile['override_id'] }}" method="POST" action="{{ route('admin.group-ticketing.tiles.destroy', $tile['override_id']) }}" class="d-none">
                    @csrf
                    @method('DELETE')
                </form>
            @endif
        @endforeach
    @endif
@endsection
