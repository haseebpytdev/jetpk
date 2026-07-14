@php
    use App\Support\Client\ClientPageMediaSchema;

    $mediaGroups = ClientPageMediaSchema::groupedFor($pageKey);
    $sectionLabels = [
        'hero' => 'Hero imagery',
        'trust_chips' => 'Trust badges',
        'why_book' => 'Why-book cards',
        'groups' => 'Group ticketing',
        'trust' => 'Trust / logos',
        'support_cta' => 'Support callout',
        'seo' => 'Share / SEO',
        'content' => 'Page imagery',
        'benefits' => 'Benefits',
        'branding' => 'Branding',
        'announcement' => 'Announcement',
    ];
@endphp
@if ($mediaGroups === [])
    <div class="jp-card jp-muted">No structured media fields for this page.</div>
@else
    <nav class="jp-page-editor__nav jp-page-editor__nav--media" aria-label="Media sections">
        @foreach ($mediaGroups as $sectionKey => $fields)
            <button type="button" class="jp-queue-tab" data-jp-media-section="{{ $sectionKey }}">{{ $sectionLabels[$sectionKey] ?? ucfirst(str_replace('_', ' ', $sectionKey)) }}</button>
        @endforeach
    </nav>
    @foreach ($mediaGroups as $sectionKey => $fields)
        <div class="jp-card jp-media-section {{ $loop->first ? '' : 'jp-is-hidden' }}" data-jp-media-panel="{{ $sectionKey }}" id="media-section-{{ $sectionKey }}">
            <h2 class="jp-card__title">{{ $sectionLabels[$sectionKey] ?? ucfirst(str_replace('_', ' ', $sectionKey)) }}</h2>
            @if ($sectionKey === 'groups' && Route::has('admin.group-ticketing.tiles.index'))
                <p class="jp-help" style="margin-bottom:12px;">
                    Group homepage tile images are managed in
                    <a href="{{ route('admin.group-ticketing.tiles.index') }}">Group Homepage Tiles</a>
                    — do not duplicate tile artwork here unless used as a section banner.
                </p>
            @endif
            <div class="jp-media-grid">
                @foreach ($fields as $field)
                    @include('themes.admin.jetpakistan.page-settings.partials.media-field', [
                        'field' => $field,
                        'pageKey' => $pageKey,
                        'assets' => $assets,
                    ])
                @endforeach
            </div>
        </div>
    @endforeach
@endif
