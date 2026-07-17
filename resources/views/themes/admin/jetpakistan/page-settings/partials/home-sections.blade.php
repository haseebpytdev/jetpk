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
    <div class="jp-between jp-section-toggle">
        <h2 class="jp-card__title" style="margin:0;">Stats strip</h2>
        <div class="jp-toggle">
            <input type="hidden" name="content[feature_board][enabled]" value="0">
            <input type="checkbox" id="feature-board-enabled" name="content[feature_board][enabled]" value="1" @checked(data_get($content, 'feature_board.enabled', '1') == '1')>
            <label for="feature-board-enabled">Section enabled</label>
        </div>
    </div>
    <div class="jp-field jp-field--inline" style="max-width:160px;">
        <label class="jp-field__label" for="feature-board-order">Position on page</label>
        <input id="feature-board-order" type="number" min="2" max="9" class="jp-control" name="content[feature_board][order]" value="{{ data_get($content, 'feature_board.order', '') }}">
        <p class="jp-field__help">Lower numbers render higher on the page. Leave blank to use the default position. Hero always renders first and is not reorderable.</p>
    </div>
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
    <div class="jp-field jp-field--inline" style="max-width:160px;">
        <label class="jp-field__label" for="trust-order">Position on page</label>
        <input id="trust-order" type="number" min="2" max="9" class="jp-control" name="content[trust][order]" value="{{ data_get($content, 'trust.order', '') }}">
        <p class="jp-field__help">Lower numbers render higher on the page. Leave blank to use the default position. Hero always renders first and is not reorderable.</p>
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
    <div class="jp-field jp-field--inline" style="max-width:160px;">
        <label class="jp-field__label" for="group-cards-order">Position on page</label>
        <input id="group-cards-order" type="number" min="2" max="9" class="jp-control" name="content[group_cards][order]" value="{{ data_get($content, 'group_cards.order', '') }}">
        <p class="jp-field__help">Lower numbers render higher on the page. Leave blank to use the default position. Hero always renders first and is not reorderable.</p>
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
                    <label class="jp-field__label">Price (PKR)</label>
                    <input aria-label="Price (PKR)" class="jp-control" name="content[group_cards][items][{{ $i }}][price]" value="{{ data_get($content, "group_cards.items.{$i}.price") }}">
                </div>
                <div class="jp-field">
                    <label class="jp-field__label">Link URL</label>
                    <input aria-label="Link URL" class="jp-control" name="content[group_cards][items][{{ $i }}][link]" value="{{ data_get($content, "group_cards.items.{$i}.link") }}">
                </div>
            </div>
            <div class="jp-toggle">
                <input type="hidden" name="content[group_cards][items][{{ $i }}][gold]" value="0">
                <input type="checkbox" id="group-card-gold-{{ $i }}" name="content[group_cards][items][{{ $i }}][gold]" value="1" @checked(data_get($content, "group_cards.items.{$i}.gold", false))>
                <label for="group-card-gold-{{ $i }}">Featured (gold badge styling)</label>
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
    <div class="jp-field jp-field--inline" style="max-width:160px;">
        <label class="jp-field__label" for="featured-deals-order">Position on page</label>
        <input id="featured-deals-order" type="number" min="2" max="9" class="jp-control" name="content[featured_deals][order]" value="{{ data_get($content, 'featured_deals.order', '') }}">
        <p class="jp-field__help">Lower numbers render higher on the page. Leave blank to use the default position. Hero always renders first and is not reorderable.</p>
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
        <p class="jp-field__help">Editorial copy only — deal cards below are CMS items, not live supplier fares.</p>
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
    <div class="jp-repeatable-list" data-jp-repeatable="featured-deals" data-jp-repeatable-max="{{ config('jetpk_homepage.max_featured_deals', 6) }}">
        <div class="jp-between">
            <p class="jp-muted" style="margin:0;">Deal cards (editorial)</p>
            <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost" data-jp-repeatable-add>Add deal card</button>
        </div>
        @php $dealItems = data_get($content, 'featured_deals.items', []); @endphp
        @foreach (is_array($dealItems) && $dealItems !== [] ? array_values($dealItems) : [] as $i => $deal)
            <div class="jp-repeatable-card" data-jp-repeatable-item>
                <div class="jp-between">
                    <p class="jp-muted" style="margin:0;">Deal {{ $i + 1 }}</p>
                    <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost" data-jp-repeatable-remove>Remove</button>
                </div>
                <input type="hidden" name="content[featured_deals][items][{{ $i }}][sort_order]" value="{{ data_get($deal, 'sort_order', $i) }}">
                <div class="jp-toggle">
                    <input type="hidden" name="content[featured_deals][items][{{ $i }}][enabled]" value="0">
                    <input type="checkbox" id="deal-enabled-{{ $i }}" name="content[featured_deals][items][{{ $i }}][enabled]" value="1" @checked(data_get($deal, 'enabled', '1') == '1')>
                    <label for="deal-enabled-{{ $i }}">Enabled</label>
                </div>
                <div class="jp-grid jp-grid--3">
                    <div class="jp-field"><label class="jp-field__label">Airline</label><input class="jp-control" name="content[featured_deals][items][{{ $i }}][airline]" value="{{ data_get($deal, 'airline') }}"></div>
                    <div class="jp-field"><label class="jp-field__label">From</label><input class="jp-control" name="content[featured_deals][items][{{ $i }}][from]" value="{{ data_get($deal, 'from') }}" maxlength="3"></div>
                    <div class="jp-field"><label class="jp-field__label">To</label><input class="jp-control" name="content[featured_deals][items][{{ $i }}][to]" value="{{ data_get($deal, 'to') }}" maxlength="3"></div>
                    <div class="jp-field"><label class="jp-field__label">Depart</label><input class="jp-control" name="content[featured_deals][items][{{ $i }}][depart]" value="{{ data_get($deal, 'depart') }}"></div>
                    <div class="jp-field"><label class="jp-field__label">Arrive</label><input class="jp-control" name="content[featured_deals][items][{{ $i }}][arrive]" value="{{ data_get($deal, 'arrive') }}"></div>
                    <div class="jp-field"><label class="jp-field__label">Duration</label><input class="jp-control" name="content[featured_deals][items][{{ $i }}][dur]" value="{{ data_get($deal, 'dur') }}"></div>
                    <div class="jp-field"><label class="jp-field__label">Stops</label><input type="number" min="0" max="9" class="jp-control" name="content[featured_deals][items][{{ $i }}][stops]" value="{{ data_get($deal, 'stops', 0) }}"></div>
                    <div class="jp-field"><label class="jp-field__label">Price (PKR)</label><input type="number" min="0" class="jp-control" name="content[featured_deals][items][{{ $i }}][price]" value="{{ data_get($deal, 'price') }}"></div>
                </div>
            </div>
        @endforeach
        <template data-jp-repeatable-template>
            <div class="jp-repeatable-card" data-jp-repeatable-item>
                <div class="jp-between">
                    <p class="jp-muted" style="margin:0;">New deal</p>
                    <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost" data-jp-repeatable-remove>Remove</button>
                </div>
                <input type="hidden" data-jp-name="content[featured_deals][items][__INDEX__][sort_order]" value="__INDEX__">
                <div class="jp-toggle">
                    <input type="hidden" data-jp-name="content[featured_deals][items][__INDEX__][enabled]" value="0">
                    <input type="checkbox" data-jp-name="content[featured_deals][items][__INDEX__][enabled]" value="1" checked>
                    <label>Enabled</label>
                </div>
                <div class="jp-grid jp-grid--3">
                    <div class="jp-field"><label class="jp-field__label">Airline</label><input class="jp-control" data-jp-name="content[featured_deals][items][__INDEX__][airline]"></div>
                    <div class="jp-field"><label class="jp-field__label">From</label><input class="jp-control" data-jp-name="content[featured_deals][items][__INDEX__][from]" maxlength="3"></div>
                    <div class="jp-field"><label class="jp-field__label">To</label><input class="jp-control" data-jp-name="content[featured_deals][items][__INDEX__][to]" maxlength="3"></div>
                    <div class="jp-field"><label class="jp-field__label">Depart</label><input class="jp-control" data-jp-name="content[featured_deals][items][__INDEX__][depart]"></div>
                    <div class="jp-field"><label class="jp-field__label">Arrive</label><input class="jp-control" data-jp-name="content[featured_deals][items][__INDEX__][arrive]"></div>
                    <div class="jp-field"><label class="jp-field__label">Duration</label><input class="jp-control" data-jp-name="content[featured_deals][items][__INDEX__][dur]"></div>
                    <div class="jp-field"><label class="jp-field__label">Stops</label><input type="number" min="0" max="9" class="jp-control" data-jp-name="content[featured_deals][items][__INDEX__][stops]" value="0"></div>
                    <div class="jp-field"><label class="jp-field__label">Price (PKR)</label><input type="number" min="0" class="jp-control" data-jp-name="content[featured_deals][items][__INDEX__][price]"></div>
                </div>
            </div>
        </template>
    </div>
</div>

@include('themes.admin.jetpakistan.page-settings.partials.home-routes-manager', ['content' => $content, 'pageKey' => $pageKey, 'assets' => $assets ?? collect()])

@include('themes.admin.jetpakistan.page-settings.partials.home-destinations-manager', ['content' => $content, 'pageKey' => $pageKey, 'assets' => $assets ?? collect()])

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
    <div class="jp-field jp-field--inline" style="max-width:160px;">
        <label class="jp-field__label" for="why-book-order">Position on page</label>
        <input id="why-book-order" type="number" min="2" max="9" class="jp-control" name="content[why_book][order]" value="{{ data_get($content, 'why_book.order', '') }}">
        <p class="jp-field__help">Lower numbers render higher on the page. Leave blank to use the default position. Hero always renders first and is not reorderable.</p>
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

{{-- JETPK-HOMEPAGE-CMS Task 9: the "Group ticketing section" panel (content[groups][*])
     that used to live here has been removed. It was a second, duplicate admin panel
     for the same visual section as "Group travel packages" above — 4 of its 5 fields
     were never read by the frontend at all, and the 5th (cta_url) was fetched and
     discarded. See docs/JETPK_HOMEPAGE_CANONICAL_SCHEMA.md for the retirement decision;
     group_cards.{subtitle,cta_text,cta_url} above are the canonical fields now, and
     groups.blade.php has been updated to read them. Any content still saved under the
     old groups.* key is migrated automatically on read by HomepageContentNormalizer
     (Task 7) — no data is lost by removing this form. --}}

@include('themes.admin.jetpakistan.page-settings.partials.home-support-cta-manager', ['content' => $content, 'pageKey' => $pageKey, 'assets' => $assets ?? collect()])
