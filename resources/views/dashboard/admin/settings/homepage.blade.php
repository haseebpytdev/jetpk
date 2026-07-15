@extends(client_layout('dashboard', 'admin'))

@php
    use App\Services\Agencies\HomepageSectionPresenter;
@endphp

@section('title', 'Homepage Sections')

@section('page-header')
    <h1 class="jp-page-title">Settings / Homepage Sections</h1>
@endsection

@section('content')
    @if (session('status'))
        <div class="jp-alert jp-alert--success">Homepage section saved.</div>
    @endif
    @if ($errors->any())
        <div class="jp-alert jp-alert--danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    @php
        $hero = $heroSection ?? [];
        $heroModel = $heroSectionModel ?? null;
        $structured = $structuredSections ?? [];
        $labels = [
            'trust_metrics' => 'Trust / stat boxes',
            'feature_cards' => 'Featured fares',
            'popular_routes' => 'Popular corridors',
            'why_choose_us' => 'Why book with us',
        ];
    @endphp

    <form method="post" action="{{ route('admin.settings.homepage.update', 'hero') }}" enctype="multipart/form-data" class="jp-card">
        @csrf
        @method('PATCH')
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="jp-card__title mb-0">Hero banner &amp; headline</h3>
            <label class="form-check m-0">
                <input type="hidden" name="is_enabled" value="0">
                <input class="form-check-input" type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $hero['enabled'] ?? true))>
                <span class="form-check-label">Section enabled</span>
            </label>
        </div>
        <div class="jp-card__body">
            <div class="jp-alert jp-alert--info small">
                <strong>Recommended hero background:</strong> 1920&times;760 px (wide desktop-safe ratio). Keep important content away from edges &mdash; the headline and floating search card overlay the center of the banner. JPG/WebP/PNG; avoid baked-in text. Mobile: use a center-focused crop.
            </div>
            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <label class="jp-label">Hero badge / label</label>
                    <input class="jp-control" name="badge" value="{{ old('badge', $hero['badge'] ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="jp-label">Hero background image</label>
                    <input type="file" class="jp-control" name="image" accept="image/jpeg,image/png,image/webp">
                    @if ($heroModel?->image_path)
                        <div class="form-text">Current: {{ $heroModel->image_path }}</div>
                    @endif
                </div>
                <div class="col-md-6">
                    <label class="jp-label">Hero headline</label>
                    <input class="jp-control" name="title" value="{{ old('title', $hero['title'] ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="jp-label">Sort order</label>
                    <input class="jp-control" type="number" name="sort_order" value="{{ old('sort_order', $heroModel?->sort_order ?? 10) }}">
                </div>
                <div class="col-12">
                    <label class="jp-label">Hero text / intro content</label>
                    <textarea class="jp-control" rows="4" name="subtitle">{{ old('subtitle', $hero['subtitle'] ?? '') }}</textarea>
                    <div class="form-text">This text appears below the homepage hero headline. Keep it short. Basic formatting/line breaks may be supported depending on configuration.</div>
                </div>
            </div>
            <div class="d-flex justify-content-end">
                <button class="jp-btn jp-btn--outline">Save hero</button>
            </div>
        </div>
    </form>

    @foreach (HomepageSectionPresenter::STRUCTURED_SECTION_KEYS as $sectionKey)
        @if ($sectionKey === 'feature_cards')
            @continue
        @endif
        @php
            $payload = $structured[$sectionKey] ?? ['enabled' => true, 'title' => '', 'subtitle' => '', 'items' => []];
            $sectionModel = $sections->firstWhere('section_key', $sectionKey);
        @endphp
        <form method="post" action="{{ route('admin.settings.homepage.update', $sectionKey) }}" class="jp-card">
            @csrf
            @method('PATCH')
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="jp-card__title mb-0">{{ $labels[$sectionKey] ?? $sectionKey }}</h3>
                <label class="form-check m-0">
                    <input type="hidden" name="is_enabled" value="0">
                    <input class="form-check-input" type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $payload['enabled'] ?? true))>
                    <span class="form-check-label">Section enabled</span>
                </label>
            </div>
            <div class="jp-card__body">
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="jp-label">Section title</label>
                        <input class="jp-control" name="title" value="{{ old('title', $payload['title'] ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="jp-label">Sort order</label>
                        <input class="jp-control" type="number" name="sort_order" value="{{ old('sort_order', $sectionModel?->sort_order ?? 100) }}">
                    </div>
                    <div class="col-12">
                        <label class="jp-label">Section subtitle</label>
                        <textarea class="jp-control" rows="2" name="subtitle">{{ old('subtitle', $payload['subtitle'] ?? '') }}</textarea>
                    </div>
                </div>

                @foreach ($payload['items'] ?? [] as $index => $item)
                    <fieldset class="border rounded p-3 mb-3">
                        <legend class="float-none w-auto px-2 fs-6 mb-0">Item {{ $index + 1 }}</legend>
                        <input type="hidden" name="items[{{ $index }}][item_key]" value="{{ $item['item_key'] ?? 'default-'.$index }}">
                        <div class="row g-2 mt-2">
                            <div class="col-md-2">
                                <label class="jp-label">Sort</label>
                                <input class="jp-control" type="number" name="items[{{ $index }}][sort_order]" value="{{ old("items.{$index}.sort_order", $item['sort_order'] ?? (($index + 1) * 10)) }}">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <label class="form-check">
                                    <input class="form-check-input" type="checkbox" name="items[{{ $index }}][is_enabled]" value="1" @checked(old("items.{$index}.is_enabled", $item['is_enabled'] ?? true))>
                                    <span class="form-check-label">Enabled</span>
                                </label>
                            </div>

                            @if ($sectionKey === 'trust_metrics')
                                <div class="col-md-2">
                                    <label class="jp-label">Value</label>
                                    <input class="jp-control" name="items[{{ $index }}][value]" value="{{ old("items.{$index}.value", $item['value'] ?? '') }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="jp-label">Label</label>
                                    <input class="jp-control" name="items[{{ $index }}][label]" value="{{ old("items.{$index}.label", $item['label'] ?? '') }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="jp-label">Icon</label>
                                    <select class="jp-control" name="items[{{ $index }}][icon]">
                                        @foreach ($iconOptions as $iconKey => $iconClass)
                                            <option value="{{ $iconKey }}" @selected(old("items.{$index}.icon", $item['icon'] ?? '') === $iconKey)>{{ $iconKey }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @elseif ($sectionKey === 'popular_routes')
                                <div class="col-md-4">
                                    <label class="jp-label">Card title</label>
                                    <input class="jp-control" name="items[{{ $index }}][label]" value="{{ old("items.{$index}.label", $item['label'] ?? '') }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="jp-label">From</label>
                                    <input class="jp-control text-uppercase" maxlength="3" name="items[{{ $index }}][from]" value="{{ old("items.{$index}.from", $item['from'] ?? '') }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="jp-label">To</label>
                                    <input class="jp-control text-uppercase" maxlength="3" name="items[{{ $index }}][to]" value="{{ old("items.{$index}.to", $item['to'] ?? '') }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="jp-label">Button URL (optional)</label>
                                    <input class="jp-control" name="items[{{ $index }}][button_url]" value="{{ old("items.{$index}.button_url", $item['button_url'] ?? '') }}" placeholder="Leave empty for search link">
                                </div>
                            @elseif ($sectionKey === 'why_choose_us')
                                <div class="col-md-4">
                                    <label class="jp-label">Title</label>
                                    <input class="jp-control" name="items[{{ $index }}][title]" value="{{ old("items.{$index}.title", $item['title'] ?? '') }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="jp-label">Icon</label>
                                    <select class="jp-control" name="items[{{ $index }}][icon]">
                                        @foreach ($iconOptions as $iconKey => $iconClass)
                                            <option value="{{ $iconKey }}" @selected(old("items.{$index}.icon", $item['icon'] ?? '') === $iconKey)>{{ $iconKey }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="jp-label">Description</label>
                                    <textarea class="jp-control" rows="2" name="items[{{ $index }}][text]">{{ old("items.{$index}.text", $item['text'] ?? '') }}</textarea>
                                </div>
                            @endif
                        </div>
                    </fieldset>
                @endforeach

                <div class="d-flex justify-content-end">
                    <button class="jp-btn jp-btn--outline">Save {{ $labels[$sectionKey] ?? $sectionKey }}</button>
                </div>
            </div>
        </form>
    @endforeach

    @include('dashboard.admin.settings.partials.homepage-featured-fare-routes', [
        'featuredFaresSection' => $structured['feature_cards'] ?? ['enabled' => true, 'title' => '', 'subtitle' => '', 'items' => []],
        'featuredFaresSectionModel' => $sections->firstWhere('section_key', 'feature_cards'),
        'featuredFares' => $featuredFares ?? collect(),
        'offsetOptions' => $featuredFareOffsetOptions ?? [3, 5, 7],
    ])

    @foreach ($sections->whereNotIn('section_key', array_merge([HomepageSectionPresenter::HERO], HomepageSectionPresenter::STRUCTURED_SECTION_KEYS)) as $section)
        <form method="post" action="{{ route('admin.settings.homepage.update', $section->section_key) }}" enctype="multipart/form-data" class="jp-card">
            @csrf
            @method('PATCH')
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="jp-card__title mb-0 text-capitalize">{{ str_replace('_', ' ', $section->section_key) }}</h3>
                <label class="form-check m-0"><input type="hidden" name="is_enabled" value="0"><input class="form-check-input" type="checkbox" name="is_enabled" value="1" @checked($section->is_enabled)><span class="form-check-label">Enabled</span></label>
            </div>
            <div class="jp-card__body">
                <div class="row g-2">
                    <div class="col-md-6"><label class="jp-label">Title</label><input class="jp-control" name="title" value="{{ old('title', $section->title) }}"></div>
                    <div class="col-md-6"><label class="jp-label">Image</label><input type="file" class="jp-control" name="image"></div>
                    <div class="col-12"><label class="jp-label">Subtitle</label><textarea class="jp-control" rows="2" name="subtitle">{{ old('subtitle', $section->subtitle) }}</textarea></div>
                    <div class="col-12"><label class="jp-label">Content (JSON)</label><textarea class="jp-control" rows="4" name="content">@json(old('content', $section->content ?? []), JSON_PRETTY_PRINT)</textarea></div>
                    <div class="col-md-3"><label class="jp-label">Sort order</label><input class="jp-control" type="number" name="sort_order" value="{{ old('sort_order', $section->sort_order) }}"></div>
                </div>
                <div class="mt-2 d-flex justify-content-end"><button class="jp-btn jp-btn--outline">Save section</button></div>
            </div>
        </form>
    @endforeach
@endsection
