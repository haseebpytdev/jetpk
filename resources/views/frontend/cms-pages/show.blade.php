@extends(client_layout('frontend', 'frontend'))

@section('title', $metaTitle)

@if ($metaDescription !== '')
    @section('meta-description', $metaDescription)
@endif

@push('head-meta')
    <link rel="canonical" href="{{ $canonicalUrl }}">
    @if ($robots === 'noindex')
        <meta name="robots" content="noindex, nofollow">
    @else
        <meta name="robots" content="index, follow">
    @endif
@endpush

@section('content')
    @if (! empty($isPreview))
        <div class="ota-cms-preview-banner" role="status">
            Preview mode — this page is not public unless active.
            @if ($page->status !== 'active')
                <span class="ota-cms-preview-banner__status">(Current status: {{ $page->status }})</span>
            @endif
        </div>
    @endif

    <section class="ota-section ota-form-page ota-cms-page" aria-labelledby="ota-cms-page-heading">
        <div class="ota-container">
            <header class="ota-section-head">
                <h1 id="ota-cms-page-heading" class="ota-section-title">{{ $page->title }}</h1>
                @if ($page->excerpt)
                    <p class="ota-section-desc">{{ $page->excerpt }}</p>
                @endif
            </header>

            @if ($page->featured_image_path)
                <figure class="ota-cms-page__featured mb-4">
                    <img src="{{ $page->featuredImageUrl() }}" alt="" class="ota-cms-page__featured-img img-fluid rounded">
                </figure>
            @endif

            @if ($bodyHtml !== '')
                <div class="ota-about-panel ota-cms-page__body">
                    {!! $bodyHtml !!}
                </div>
            @endif

            @if ($page->updated_at)
                <p class="ota-cms-page__updated text-secondary small mt-4 mb-0">
                    Last updated {{ $page->updated_at->format('F j, Y') }}
                </p>
            @endif
        </div>
    </section>
@endsection

@push('styles')
    <style>
        .ota-cms-preview-banner {
            background: #fef3c7;
            border-bottom: 1px solid #f59e0b;
            color: #92400e;
            font-size: 0.875rem;
            font-weight: 600;
            padding: 0.65rem 1rem;
            text-align: center;
        }
        .ota-cms-preview-banner__status { font-weight: 500; }
        .ota-cms-page__featured-img {
            display: block;
            max-height: 360px;
            object-fit: cover;
            width: 100%;
        }
        .ota-cms-page__body { overflow-wrap: anywhere; }
        .ota-cms-page__body :is(h1, h2, h3, h4) { margin-top: 1.25rem; }
        .ota-cms-page__body p { margin-bottom: 0.75rem; }
        .ota-cms-page__body ul, .ota-cms-page__body ol { margin-bottom: 1rem; padding-left: 1.25rem; }
        .ota-cms-page__body table { display: block; max-width: 100%; overflow-x: auto; }
    </style>
@endpush
