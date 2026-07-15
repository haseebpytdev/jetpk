@php
    use App\Support\Client\ClientPageKeys;
@endphp

{{-- Hero --}}
<div class="jp-card jp-page-section" id="section-hero" data-jp-section-panel="hero">
    <h2 class="jp-card__title">Hero</h2>
    <div class="jp-field">
        <label class="jp-field__label" for="hero-eyebrow">Eyebrow</label>
        <input id="hero-eyebrow" class="jp-control" name="content[hero][eyebrow]" value="{{ data_get($content, 'hero.eyebrow') }}">
        <p class="jp-field__help">Leave empty to hide this text on the public homepage.</p>
    </div>
    <div class="jp-grid jp-grid--2">
        <div class="jp-field">
            <label class="jp-field__label" for="hero-headline">Headline</label>
            <input id="hero-headline" class="jp-control" name="content[hero][headline]" value="{{ data_get($content, 'hero.headline') }}">
        </div>
        <div class="jp-field">
            <label class="jp-field__label" for="hero-highlight">Highlighted line</label>
            <input id="hero-highlight" class="jp-control" name="content[hero][headline_highlight]" value="{{ data_get($content, 'hero.headline_highlight') }}">
        </div>
    </div>
    <div class="jp-field">
        <label class="jp-field__label" for="hero-subtitle">Subtitle</label>
        <textarea id="hero-subtitle" class="jp-control jp-control--textarea" rows="3" name="content[hero][subtitle]">{{ data_get($content, 'hero.subtitle') }}</textarea>
        <p class="jp-field__help">Leave empty to hide this text on the public homepage.</p>
    </div>
    <div class="jp-grid jp-grid--2">
        <div class="jp-field">
            <label class="jp-field__label" for="hero-cta1-text">Primary CTA text</label>
            <input id="hero-cta1-text" class="jp-control" name="content[hero][cta_primary_text]" value="{{ data_get($content, 'hero.cta_primary_text') }}">
        </div>
        <div class="jp-field">
            <label class="jp-field__label" for="hero-cta1-url">Primary CTA URL</label>
            <input id="hero-cta1-url" class="jp-control" name="content[hero][cta_primary_url]" value="{{ data_get($content, 'hero.cta_primary_url') }}">
        </div>
        <div class="jp-field">
            <label class="jp-field__label" for="hero-cta2-text">Secondary CTA text</label>
            <input id="hero-cta2-text" class="jp-control" name="content[hero][cta_secondary_text]" value="{{ data_get($content, 'hero.cta_secondary_text') }}">
        </div>
        <div class="jp-field">
            <label class="jp-field__label" for="hero-cta2-url">Secondary CTA URL</label>
            <input id="hero-cta2-url" class="jp-control" name="content[hero][cta_secondary_url]" value="{{ data_get($content, 'hero.cta_secondary_url') }}">
        </div>
    </div>
    <div class="jp-toggle">
        <input type="hidden" name="content[hero][search_visible]" value="0">
        <input type="checkbox" id="hero-search-visible" name="content[hero][search_visible]" value="1" @checked(data_get($content, 'hero.search_visible', '1') == '1')>
        <label class="jp-field__label" for="hero-search-visible">Show flight search on homepage hero</label>
    </div>
</div>

{{-- Trust badges --}}
<div class="jp-card jp-page-section jp-is-hidden" id="section-trust-chips" data-jp-section-panel="trust-chips">
    <h2 class="jp-card__title">Trust badges</h2>
    <p class="jp-field__help">Leave a badge label empty to hide it publicly.</p>
    @foreach (range(0, 3) as $i)
        <div class="jp-field">
            <label class="jp-field__label" for="trust-chip-{{ $i }}">Badge {{ $i + 1 }}</label>
            <input id="trust-chip-{{ $i }}" class="jp-control" name="content[trust_chips][{{ $i }}][label]" value="{{ data_get($content, "trust_chips.{$i}.label") }}">
        </div>
    @endforeach
</div>

{{-- Stats strip --}}
<div class="jp-card jp-page-section jp-is-hidden" id="section-feature-board" data-jp-section-panel="feature-board">
    <h2 class="jp-card__title">Stats strip</h2>
    <p class="jp-field__help">Leave value and label empty to hide a stat on the public homepage.</p>
    @foreach (range(0, 4) as $i)
        <div class="jp-repeatable-card">
            <p class="jp-muted">Stat {{ $i + 1 }}</p>
            <div class="jp-grid jp-grid--2">
                <div class="jp-field">
                    <label class="jp-field__label">Value</label>
                    <input aria-label="Value" class="jp-control" name="content[feature_board][items][{{ $i }}][value]" value="{{ data_get($content, "feature_board.items.{$i}.value") }}" placeholder="e.g. 400+">
                </div>
                <div class="jp-field">
                    <label class="jp-field__label">Label</label>
                    <input aria-label="Label" class="jp-control" name="content[feature_board][items][{{ $i }}][label]" value="{{ data_get($content, "feature_board.items.{$i}.label") }}" placeholder="e.g. Airlines">
                </div>
            </div>
        </div>
    @endforeach
</div>

{{-- Why travellers stay (trust cards section) --}}
<div class="jp-card jp-page-section jp-is-hidden" id="section-trust" data-jp-section-panel="trust">
    <div class="jp-between jp-section-toggle">
        <h2 class="jp-card__title" style="margin:0;">Why travellers stay</h2>
        <div class="jp-toggle">
            <input type="hidden" name="content[trust][enabled]" value="0">
            <input type="checkbox" id="trust-enabled" name="content[trust][enabled]" value="1" @checked(data_get($content, 'trust.enabled', '1') == '1')>
            <label for="trust-enabled">Section enabled</label>
        </div>
    </div>
    <div class="jp-field">
        <label class="jp-field__label" for="trust-eyebrow">Eyebrow</label>
        <input id="trust-eyebrow" class="jp-control" name="content[trust][eyebrow]" value="{{ data_get($content, 'trust.eyebrow') }}">
    </div>
    <div class="jp-field">
        <label class="jp-field__label" for="trust-title">Heading</label>
        <input id="trust-title" class="jp-control" name="content[trust][title]" value="{{ data_get($content, 'trust.title') }}">
    </div>
    <div class="jp-field">
        <label class="jp-field__label" for="trust-subtitle">Subtitle</label>
        <textarea id="trust-subtitle" class="jp-control jp-control--textarea" rows="2" name="content[trust][subtitle]">{{ data_get($content, 'trust.subtitle') }}</textarea>
    </div>
    @foreach (range(0, 2) as $i)
        <div class="jp-repeatable-card">
            <div class="jp-between">
                <p class="jp-muted" style="margin:0;">Card {{ $i + 1 }}</p>
                <div class="jp-toggle">
                    <input type="hidden" name="content[trust][cards][{{ $i }}][enabled]" value="0">
                    <input type="checkbox" id="trust-card-enabled-{{ $i }}" name="content[trust][cards][{{ $i }}][enabled]" value="1" @checked(data_get($content, "trust.cards.{$i}.enabled", '1') == '1')>
                    <label for="trust-card-enabled-{{ $i }}">Enabled</label>
                </div>
            </div>
            <input type="hidden" name="content[trust][cards][{{ $i }}][sort_order]" value="{{ $i }}">
            <div class="jp-grid jp-grid--2">
                <div class="jp-field">
                    <label class="jp-field__label">Icon</label>
                    <input aria-label="Icon" class="jp-control" name="content[trust][cards][{{ $i }}][icon]" value="{{ data_get($content, "trust.cards.{$i}.icon") }}" placeholder="check-square">
                </div>
                <div class="jp-field">
                    <label class="jp-field__label">Title</label>
                    <input aria-label="Title" class="jp-control" name="content[trust][cards][{{ $i }}][title]" value="{{ data_get($content, "trust.cards.{$i}.title") }}">
                </div>
            </div>
            <div class="jp-field">
                <label class="jp-field__label">Description</label>
                <textarea aria-label="Description" class="jp-control jp-control--textarea" rows="2" name="content[trust][cards][{{ $i }}][text]">{{ data_get($content, "trust.cards.{$i}.text") }}</textarea>
            </div>
        </div>
    @endforeach
</div>

{{-- Built for how Pakistan books --}}
<div class="jp-card jp-page-section jp-is-hidden" id="section-why-book" data-jp-section-panel="why-book">
    <div class="jp-between jp-section-toggle">
        <h2 class="jp-card__title" style="margin:0;">Built for how Pakistan books</h2>
        <div class="jp-toggle">
            <input type="hidden" name="content[why_book][enabled]" value="0">
            <input type="checkbox" id="why-book-enabled" name="content[why_book][enabled]" value="1" @checked(data_get($content, 'why_book.enabled', '1') == '1')>
            <label for="why-book-enabled">Section enabled</label>
        </div>
    </div>
    <div class="jp-field">
        <label class="jp-field__label" for="why-book-eyebrow">Eyebrow</label>
        <input id="why-book-eyebrow" class="jp-control" name="content[why_book][eyebrow]" value="{{ data_get($content, 'why_book.eyebrow') }}">
    </div>
    <div class="jp-field">
        <label class="jp-field__label" for="why-book-title">Heading</label>
        <input id="why-book-title" class="jp-control" name="content[why_book][title]" value="{{ data_get($content, 'why_book.title') }}">
    </div>
    <div class="jp-field">
        <label class="jp-field__label" for="why-book-subtitle">Subtitle</label>
        <textarea id="why-book-subtitle" class="jp-control jp-control--textarea" rows="2" name="content[why_book][subtitle]">{{ data_get($content, 'why_book.subtitle') }}</textarea>
    </div>
    @foreach (range(0, 3) as $i)
        <div class="jp-repeatable-card">
            <div class="jp-between">
                <p class="jp-muted" style="margin:0;">Card {{ $i + 1 }}</p>
                <div class="jp-toggle">
                    <input type="hidden" name="content[why_book][cards][{{ $i }}][enabled]" value="0">
                    <input type="checkbox" id="why-card-enabled-{{ $i }}" name="content[why_book][cards][{{ $i }}][enabled]" value="1" @checked(data_get($content, "why_book.cards.{$i}.enabled", '1') == '1')>
                    <label for="why-card-enabled-{{ $i }}">Enabled</label>
                </div>
            </div>
            <input type="hidden" name="content[why_book][cards][{{ $i }}][sort_order]" value="{{ $i }}">
            <div class="jp-grid jp-grid--3">
                <div class="jp-field">
                    <label class="jp-field__label">Number label</label>
                    <input aria-label="Number label" class="jp-control" name="content[why_book][cards][{{ $i }}][num]" value="{{ data_get($content, "why_book.cards.{$i}.num") }}" placeholder="01 · Pricing">
                </div>
                <div class="jp-field">
                    <label class="jp-field__label">Title</label>
                    <input aria-label="Title" class="jp-control" name="content[why_book][cards][{{ $i }}][title]" value="{{ data_get($content, "why_book.cards.{$i}.title") }}">
                </div>
                <div class="jp-field">
                    <label class="jp-field__label">Icon</label>
                    <input aria-label="Icon" class="jp-control" name="content[why_book][cards][{{ $i }}][icon]" value="{{ data_get($content, "why_book.cards.{$i}.icon") }}">
                </div>
            </div>
            <div class="jp-field">
                <label class="jp-field__label">Description</label>
                <textarea aria-label="Description" class="jp-control jp-control--textarea" rows="2" name="content[why_book][cards][{{ $i }}][text]">{{ data_get($content, "why_book.cards.{$i}.text") }}</textarea>
            </div>
        </div>
    @endforeach
</div>

@include('themes.admin.jetpakistan.page-settings.partials.home-routes-manager', ['content' => $content, 'pageKey' => $pageKey, 'assets' => $assets ?? collect()])

@include('themes.admin.jetpakistan.page-settings.partials.home-destinations-manager', ['content' => $content, 'pageKey' => $pageKey, 'assets' => $assets ?? collect()])

{{-- Group travel cards --}}
<div class="jp-card jp-page-section jp-is-hidden" id="section-group-cards" data-jp-section-panel="group-cards">
    <div class="jp-between jp-section-toggle">
        <h2 class="jp-card__title" style="margin:0;">Group travel packages</h2>
        <div class="jp-toggle">
            <input type="hidden" name="content[group_cards][enabled]" value="0">
            <input type="checkbox" id="group-cards-enabled" name="content[group_cards][enabled]" value="1" @checked(data_get($content, 'group_cards.enabled', '1') == '1')>
            <label for="group-cards-enabled">Enabled</label>
        </div>
    </div>
    <div class="jp-grid jp-grid--2">
        <div class="jp-field">
            <label class="jp-field__label" for="group-eyebrow">Eyebrow</label>
            <input id="group-eyebrow" class="jp-control" name="content[group_cards][eyebrow]" value="{{ data_get($content, 'group_cards.eyebrow') }}">
        </div>
        <div class="jp-field">
            <label class="jp-field__label" for="group-title">Heading</label>
            <input id="group-title" class="jp-control" name="content[group_cards][title]" value="{{ data_get($content, 'group_cards.title') }}">
        </div>
    </div>
    <div class="jp-field">
        <label class="jp-field__label" for="group-subtitle">Subtitle</label>
        <textarea id="group-subtitle" class="jp-control jp-control--textarea" rows="2" name="content[group_cards][subtitle]">{{ data_get($content, 'group_cards.subtitle') }}</textarea>
    </div>
    <div class="jp-grid jp-grid--2">
        <div class="jp-field">
            <label class="jp-field__label" for="group-cta-text">CTA label</label>
            <input id="group-cta-text" class="jp-control" name="content[group_cards][cta_text]" value="{{ data_get($content, 'group_cards.cta_text') }}">
        </div>
        <div class="jp-field">
            <label class="jp-field__label" for="group-cta-url">CTA URL</label>
            <input id="group-cta-url" class="jp-control" name="content[group_cards][cta_url]" value="{{ data_get($content, 'group_cards.cta_url') }}">
        </div>
    </div>
    @foreach (range(0, 2) as $i)
        <div class="jp-repeatable-card">
            <div class="jp-between">
                <p class="jp-muted" style="margin:0;">Card {{ $i + 1 }}</p>
                <div class="jp-toggle">
                    <input type="hidden" name="content[group_cards][items][{{ $i }}][enabled]" value="0">
                    <input type="checkbox" id="group-card-enabled-{{ $i }}" name="content[group_cards][items][{{ $i }}][enabled]" value="1" @checked(data_get($content, "group_cards.items.{$i}.enabled", '1') == '1')>
                    <label for="group-card-enabled-{{ $i }}">Enabled</label>
                </div>
            </div>
            <input type="hidden" name="content[group_cards][items][{{ $i }}][sort_order]" value="{{ $i }}">
            <p class="jp-field__help">Image: Media tab key <code>group_card_{{ $i + 1 }}</code> — upload, choose library, or remove there.</p>
            <div class="jp-grid jp-grid--2">
                <div class="jp-field">
                    <label class="jp-field__label">Title</label>
                    <input aria-label="Title" class="jp-control" name="content[group_cards][items][{{ $i }}][title]" value="{{ data_get($content, "group_cards.items.{$i}.title") }}">
                </div>
                <div class="jp-field">
                    <label class="jp-field__label">Badge</label>
                    <input aria-label="Badge" class="jp-control" name="content[group_cards][items][{{ $i }}][badge]" value="{{ data_get($content, "group_cards.items.{$i}.badge") }}">
                </div>
                <div class="jp-field">
                    <label class="jp-field__label">Traveller / seat text</label>
                    <input aria-label="Traveller / seat text" class="jp-control" name="content[group_cards][items][{{ $i }}][meta]" value="{{ data_get($content, "group_cards.items.{$i}.meta") }}">
                </div>
                <div class="jp-field">
                    <label class="jp-field__label">Route / destination</label>
                    <input aria-label="Route / destination" class="jp-control" name="content[group_cards][items][{{ $i }}][route]" value="{{ data_get($content, "group_cards.items.{$i}.route") }}">
                </div>
                <div class="jp-field">
                    <label class="jp-field__label">Price (PKR)</label>
                    <input aria-label="Price (PKR)" class="jp-control" name="content[group_cards][items][{{ $i }}][price]" value="{{ data_get($content, "group_cards.items.{$i}.price") }}">
                </div>
                <div class="jp-field">
                    <label class="jp-field__label">Link URL</label>
                    <input aria-label="Link URL" class="jp-control" name="content[group_cards][items][{{ $i }}][link]" value="{{ data_get($content, "group_cards.items.{$i}.link") }}">
                </div>
            </div>
            <div class="jp-field">
                <label class="jp-field__label">Image alt text</label>
                <input aria-label="Image alt text" class="jp-control" name="content[group_cards][items][{{ $i }}][alt]" value="{{ data_get($content, "group_cards.items.{$i}.alt") }}">
            </div>
        </div>
    @endforeach
</div>

{{-- Featured deals --}}
<div class="jp-card jp-page-section jp-is-hidden" id="section-featured-deals" data-jp-section-panel="featured-deals">
    <div class="jp-between jp-section-toggle">
        <h2 class="jp-card__title" style="margin:0;">Featured deals</h2>
        <div class="jp-toggle">
            <input type="hidden" name="content[featured_deals][enabled]" value="0">
            <input type="checkbox" id="featured-deals-enabled" name="content[featured_deals][enabled]" value="1" @checked(data_get($content, 'featured_deals.enabled', '1') == '1')>
            <label for="featured-deals-enabled">Visible</label>
        </div>
    </div>
    <div class="jp-grid jp-grid--2">
        <div class="jp-field">
            <label class="jp-field__label" for="deals-eyebrow">Eyebrow</label>
            <input id="deals-eyebrow" class="jp-control" name="content[featured_deals][eyebrow]" value="{{ data_get($content, 'featured_deals.eyebrow') }}">
        </div>
        <div class="jp-field">
            <label class="jp-field__label" for="deals-title">Heading</label>
            <input id="deals-title" class="jp-control" name="content[featured_deals][title]" value="{{ data_get($content, 'featured_deals.title') }}">
        </div>
    </div>
    <div class="jp-field">
        <label class="jp-field__label" for="deals-subtitle">Subtitle</label>
        <textarea id="deals-subtitle" class="jp-control jp-control--textarea" rows="2" name="content[featured_deals][subtitle]">{{ data_get($content, 'featured_deals.subtitle') }}</textarea>
    </div>
    <div class="jp-grid jp-grid--3">
        <div class="jp-field">
            <label class="jp-field__label" for="deals-cta-text">CTA label</label>
            <input id="deals-cta-text" class="jp-control" name="content[featured_deals][cta_text]" value="{{ data_get($content, 'featured_deals.cta_text') }}">
        </div>
        <div class="jp-field">
            <label class="jp-field__label" for="deals-cta-url">CTA URL</label>
            <input id="deals-cta-url" class="jp-control" name="content[featured_deals][cta_url]" value="{{ data_get($content, 'featured_deals.cta_url') }}">
        </div>
        <div class="jp-field">
            <label class="jp-field__label" for="deals-count">Card count</label>
            <input id="deals-count" type="number" min="1" max="6" class="jp-control" name="content[featured_deals][card_count]" value="{{ data_get($content, 'featured_deals.card_count', '3') }}">
        </div>
    </div>
    <div class="jp-field">
        <label class="jp-field__label" for="deals-source">Source behavior</label>
        <select id="deals-source" class="jp-control jp-control--select" name="content[featured_deals][source]">
            @foreach (['demo' => 'Demo fares (editorial fallback)', 'featured_fares' => 'Homepage featured fares table', 'hybrid' => 'Featured fares with demo fallback'] as $value => $label)
                <option value="{{ $value }}" @selected(data_get($content, 'featured_deals.source', 'hybrid') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <p class="jp-field__help">Manage live route rules under Settings → Homepage featured fares.</p>
    </div>
</div>

{{-- Group ticketing CTA --}}
<div class="jp-card jp-page-section jp-is-hidden" id="section-groups" data-jp-section-panel="groups">
    <div class="jp-between jp-section-toggle">
        <h2 class="jp-card__title" style="margin:0;">Group ticketing section</h2>
        <div class="jp-toggle">
            <input type="hidden" name="content[groups][enabled]" value="0">
            <input type="checkbox" id="groups-enabled" name="content[groups][enabled]" value="1" @checked(data_get($content, 'groups.enabled', '1') == '1')>
            <label for="groups-enabled">Enabled</label>
        </div>
    </div>
    <div class="jp-field">
        <label class="jp-field__label" for="groups-title">Title</label>
        <input id="groups-title" class="jp-control" name="content[groups][title]" value="{{ data_get($content, 'groups.title') }}">
    </div>
    <div class="jp-field">
        <label class="jp-field__label" for="groups-subtitle">Subtitle</label>
        <textarea id="groups-subtitle" class="jp-control jp-control--textarea" rows="2" name="content[groups][subtitle]">{{ data_get($content, 'groups.subtitle') }}</textarea>
    </div>
    <div class="jp-grid jp-grid--2">
        <div class="jp-field">
            <label class="jp-field__label" for="groups-cta-text">CTA text</label>
            <input id="groups-cta-text" class="jp-control" name="content[groups][cta_text]" value="{{ data_get($content, 'groups.cta_text') }}">
        </div>
        <div class="jp-field">
            <label class="jp-field__label" for="groups-cta-url">CTA URL</label>
            <input id="groups-cta-url" class="jp-control" name="content[groups][cta_url]" value="{{ data_get($content, 'groups.cta_url') }}">
        </div>
    </div>
</div>

@include('themes.admin.jetpakistan.page-settings.partials.home-support-cta-manager', ['content' => $content, 'pageKey' => $pageKey, 'assets' => $assets ?? collect()])
