@php
    $showFooterFields = old('show_in_footer', $cmsPage->show_in_footer ?? false);
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="jp-card" x-data="{ showFooter: @json((bool) $showFooterFields) }">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    <div class="jp-card__body">
        <div class="row g-3">
            <div class="col-md-8">
                <label class="jp-label">Title</label>
                <input type="text" name="title" class="jp-control" value="{{ old('title', $cmsPage->title) }}" required maxlength="180">
            </div>
            <div class="col-md-4">
                <label class="jp-label">Slug</label>
                <input type="text" name="slug" class="jp-control" value="{{ old('slug', $cmsPage->slug) }}" maxlength="180" placeholder="auto-from-title">
                <div class="form-hint">Lowercase, URL-safe. Leave blank to generate from title.</div>
            </div>

            <div class="col-12">
                <label class="jp-label">Excerpt</label>
                <textarea name="excerpt" class="jp-control" rows="2" maxlength="500">{{ old('excerpt', $cmsPage->excerpt) }}</textarea>
                <div class="form-hint">Short summary for SEO fallback and optional lead text.</div>
            </div>

            <div class="col-12">
                <label class="jp-label">Content (HTML allowed)</label>
                <textarea name="content" class="jp-control font-monospace" rows="14">{{ old('content', $cmsPage->content) }}</textarea>
                <div class="form-hint">Scripts and unsafe markup are stripped on save. Use headings, lists, links, and tables.</div>
            </div>

            <div class="col-md-6">
                <label class="jp-label">Featured image</label>
                <input type="file" name="featured_image" class="jp-control" accept="image/jpeg,image/png,image/webp">
                <div class="form-hint">JPG, PNG, or WebP — max 2 MB.</div>
                @if ($cmsPage->featured_image_path)
                    <div class="mt-2">
                        <img src="{{ $cmsPage->featuredImageUrl() }}" alt="" class="rounded border" style="max-height: 120px;">
                    </div>
                @endif
            </div>

            <div class="col-md-3">
                <label class="jp-label">Status</label>
                <select name="status" class="jp-control" required>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(old('status', $cmsPage->status ?? 'draft') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="jp-label">Robots</label>
                <select name="robots" class="jp-control" required>
                    @foreach ($robotsOptions as $robots)
                        <option value="{{ $robots }}" @selected(old('robots', $cmsPage->robots ?? 'index') === $robots)>{{ $robots }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-6">
                <label class="jp-label">SEO title</label>
                <input type="text" name="seo_title" class="jp-control" value="{{ old('seo_title', $cmsPage->seo_title) }}" maxlength="180">
            </div>
            <div class="col-md-6">
                <label class="jp-label">SEO meta description</label>
                <input type="text" name="seo_description" class="jp-control" value="{{ old('seo_description', $cmsPage->seo_description) }}" maxlength="255">
            </div>
            <div class="col-12">
                <label class="jp-label">Canonical URL</label>
                <input type="url" name="canonical_url" class="jp-control" value="{{ old('canonical_url', $cmsPage->canonical_url) }}" maxlength="255" placeholder="{{ $cmsPage->exists ? $cmsPage->route_url : url('/pages/your-slug') }}">
            </div>

            <div class="col-12"><hr class="my-1"><h3 class="h4">Footer placement (stored only)</h3></div>

            <div class="col-md-3">
                <label class="form-check form-switch mt-2">
                    <input type="hidden" name="show_in_footer" value="0">
                    <input type="checkbox" name="show_in_footer" value="1" class="form-check-input" x-model="showFooter" @checked(old('show_in_footer', $cmsPage->show_in_footer ?? false))>
                    <span class="form-check-label">Show in footer</span>
                </label>
            </div>
            <div class="col-md-3" x-show="showFooter" x-cloak>
                <label class="jp-label">Footer group</label>
                <select name="footer_group" class="jp-control">
                    <option value="">—</option>
                    @foreach ($footerGroups as $group)
                        <option value="{{ $group }}" @selected(old('footer_group', $cmsPage->footer_group) === $group)>{{ str_replace('_', ' ', ucfirst($group)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3" x-show="showFooter" x-cloak>
                <label class="jp-label">Footer label</label>
                <input type="text" name="footer_label" class="jp-control" value="{{ old('footer_label', $cmsPage->footer_label) }}" maxlength="120" placeholder="Defaults to title">
            </div>
            <div class="col-md-3" x-show="showFooter" x-cloak>
                <label class="jp-label">Footer sort order</label>
                <input type="number" name="footer_sort_order" class="jp-control" min="0" max="9999" value="{{ old('footer_sort_order', $cmsPage->footer_sort_order ?? 0) }}">
            </div>
            <div class="col-md-3" x-show="showFooter" x-cloak>
                <label class="form-check form-switch mt-4">
                    <input type="hidden" name="open_in_new_tab" value="0">
                    <input type="checkbox" name="open_in_new_tab" value="1" class="form-check-input" @checked(old('open_in_new_tab', $cmsPage->open_in_new_tab ?? false))>
                    <span class="form-check-label">Open in new tab</span>
                </label>
            </div>

            @if ($cmsPage->exists)
                <div class="col-12">
                    <div class="text-secondary small">
                        Created {{ $cmsPage->created_at?->format('Y-m-d H:i') ?? '—' }}
                        @if ($cmsPage->creator)
                            by {{ $cmsPage->creator->name }}
                        @endif
                        · Last updated {{ $cmsPage->updated_at?->format('Y-m-d H:i') ?? '—' }}
                        @if ($cmsPage->updater)
                            by {{ $cmsPage->updater->name }}
                        @endif
                        @if ($cmsPage->published_at)
                            · Published {{ $cmsPage->published_at->format('Y-m-d H:i') }}
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
    <div class="card-footer d-flex justify-content-between">
        <a href="{{ route('admin.cms-pages.index') }}" class="jp-btn jp-btn--ghost">Cancel</a>
        <button type="submit" class="jp-btn jp-btn--primary">{{ $cmsPage->exists ? 'Save changes' : 'Create page' }}</button>
    </div>
</form>
