@extends(client_layout('dashboard', 'admin'))

@php
    $menuByKey = collect($footer['menu_sections'] ?? [])->keyBy('section_key');
@endphp

@section('title', 'Footer Settings')

@section('page-header')
    <h1 class="jp-page-title">Branding / Footer</h1>
@endsection

@section('content')
    <style>
        .footer-settings .card { margin-bottom: 0.75rem; }
        .footer-settings .card-header { min-height: 2.5rem; }
        .footer-settings .jp-label { margin-bottom: 0.25rem; font-size: 0.75rem; line-height: 1.5; font-weight: 400; }
        .footer-settings .footer-link-row { padding: 0.65rem 0.75rem; margin-bottom: 0.5rem; border: 1px solid rgba(98, 105, 118, 0.18); border-radius: 0.375rem; background: #fff; }
        .footer-settings .footer-url-summary { max-width: 10rem; min-width: 4rem; display: inline-block; vertical-align: middle; }
        .footer-settings .footer-url-btn.btn-outline-primary { border-color: var(--tblr-primary); }
        .footer-settings .footer-row-head { font-size: 0.75rem; line-height: 1.5; letter-spacing: 0.04em; text-transform: uppercase; color: #64748b; font-weight: 400; }
        .footer-settings .footer-social-row { padding: 0.45rem 0; border-bottom: 1px solid rgba(98, 105, 118, 0.12); }
        .footer-settings .footer-social-row:last-child { border-bottom: 0; }
        .footer-settings .footer-social-label { min-width: 5.5rem; }
        .footer-settings .footer-sort-input { width: 4.25rem; }
        .footer-settings .accordion-button { font-size: 0.95rem; font-weight: 600; padding: 0.65rem 1rem; }
        .footer-settings .footer-action-bar { border-top: 1px solid rgba(98, 105, 118, 0.15); background: #f8fafc; }
    </style>

    @if (session('status') === 'footer-settings-updated')
        <div class="jp-alert jp-alert--success py-2 mb-2">Footer settings saved.</div>
    @endif
    @if ($errors->any())
        <div class="jp-alert jp-alert--danger py-2 mb-2"><ul class="mb-0 small">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="alert alert-light border py-2 mb-3 small footer-settings">
        Add menu or legal links using paths like <code>/privacy-policy</code>. Disabled links stay hidden on the public site until enabled.
    </div>

    <form method="post" action="{{ route('admin.settings.branding.footer.update') }}" enctype="multipart/form-data" class="footer-settings">
        @csrf
        @method('PATCH')

        <div class="row g-3 mb-2">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h3 class="jp-card__title mb-0">Brand / About</h3>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="form-check form-switch m-0">
                                <input type="hidden" name="brand[show_logo]" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" name="brand[show_logo]" value="1" id="brand_show_logo" @checked(old('brand.show_logo', $footer['brand']['show_logo'] ?? true))>
                                <label class="form-check-label" for="brand_show_logo">Show logo</label>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input type="hidden" name="brand[use_brand_logo]" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" name="brand[use_brand_logo]" value="1" id="brand_use_brand_logo" @checked(old('brand.use_brand_logo', $footer['brand']['use_brand_logo'] ?? true))>
                                <label class="form-check-label" for="brand_use_brand_logo">Prefer main brand logo</label>
                            </div>
                        </div>
                    </div>
                    <div class="card-body row g-2">
                        <div class="col-md-6">
                            <label class="jp-label">Brand name</label>
                            <input class="jp-control jp-control-sm" name="brand[name]" value="{{ old('brand.name', $footer['brand']['name'] ?? '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="jp-label">Footer logo</label>
                            <input class="jp-control jp-control-sm" type="file" name="footer_logo" accept="image/jpeg,image/png,image/webp">
                            @if ($settings->footer_logo_path)
                                <div class="form-text">Current footer logo uploaded.</div>
                            @endif
                        </div>
                        <div class="col-12">
                            <label class="jp-label">Description</label>
                            <textarea class="jp-control jp-control-sm" rows="2" name="brand[description]">{{ old('brand.description', $footer['brand']['description'] ?? '') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h3 class="jp-card__title mb-0">Support highlight card</h3>
                        <div class="form-check form-switch m-0">
                            <input type="hidden" name="support_card[is_enabled]" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" name="support_card[is_enabled]" value="1" id="support_card_is_enabled" @checked(old('support_card.is_enabled', $footer['support_card']['is_enabled'] ?? true))>
                            <label class="form-check-label" for="support_card_is_enabled">Enabled</label>
                        </div>
                    </div>
                    <div class="card-body row g-2">
                        <div class="col-md-5">
                            <label class="jp-label">Title</label>
                            <input class="jp-control jp-control-sm" name="support_card[title]" value="{{ old('support_card.title', $footer['support_card']['title'] ?? '') }}">
                        </div>
                        <div class="col-md-5">
                            <label class="jp-label">Subtitle</label>
                            <input class="jp-control jp-control-sm" name="support_card[subtitle]" value="{{ old('support_card.subtitle', $footer['support_card']['subtitle'] ?? '') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="jp-label">Icon</label>
                            <select class="jp-control jp-control-sm" name="support_card[icon]">
                                @foreach (['headphones', 'phone', 'life-ring'] as $icon)
                                    <option value="{{ $icon }}" @selected(old('support_card.icon', $footer['support_card']['icon'] ?? 'headphones') === $icon)>{{ $icon }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-2">
            <div class="card-header py-2">
                <h3 class="jp-card__title mb-0">Menu sections</h3>
            </div>
            <div class="card-body p-2">
                <div class="accordion accordion-flush" id="footer-menu-accordion">
                    @foreach ($menuSectionKeys as $loopIndex => $sectionKey)
                        @php
                            $section = $menuByKey->get($sectionKey, ['heading' => ucfirst($sectionKey), 'items' => [], 'is_enabled' => true, 'sort_order' => 10]);
                            $accordionId = 'footer-menu-'.$sectionKey;
                        @endphp
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="{{ $accordionId }}-head">
                                <button class="accordion-button {{ $loopIndex === 0 ? '' : 'collapsed' }} py-2" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $accordionId }}-body" aria-expanded="{{ $loopIndex === 0 ? 'true' : 'false' }}" aria-controls="{{ $accordionId }}-body">
                                    {{ $section['heading'] ?? ucfirst($sectionKey) }}
                                </button>
                            </h2>
                            <div id="{{ $accordionId }}-body" class="accordion-collapse collapse {{ $loopIndex === 0 ? 'show' : '' }}" aria-labelledby="{{ $accordionId }}-head" data-bs-parent="#footer-menu-accordion">
                                <div class="accordion-body pt-2 pb-3">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                        <div class="row g-2 flex-grow-1">
                                            <div class="col-md-7">
                                                <label class="jp-label">Heading</label>
                                                <input class="jp-control jp-control-sm" name="menu_sections[{{ $sectionKey }}][heading]" value="{{ old("menu_sections.{$sectionKey}.heading", $section['heading'] ?? '') }}">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="jp-label">Sort order</label>
                                                <input class="jp-control jp-control-sm" type="number" name="menu_sections[{{ $sectionKey }}][sort_order]" value="{{ old("menu_sections.{$sectionKey}.sort_order", $section['sort_order'] ?? 10) }}">
                                            </div>
                                        </div>
                                        <div class="form-check form-switch m-0">
                                            <input type="hidden" name="menu_sections[{{ $sectionKey }}][is_enabled]" value="0">
                                            <input class="form-check-input" type="checkbox" role="switch" name="menu_sections[{{ $sectionKey }}][is_enabled]" value="1" id="menu_section_{{ $sectionKey }}_enabled" @checked(old("menu_sections.{$sectionKey}.is_enabled", $section['is_enabled'] ?? true))>
                                            <label class="form-check-label" for="menu_section_{{ $sectionKey }}_enabled">Section enabled</label>
                                        </div>
                                    </div>

                                    <div class="row g-2 footer-row-head mb-1 d-none d-md-flex">
                                        <div class="col">Label</div>
                                        <div class="col-auto" style="min-width: 9rem;">URL</div>
                                        <div class="col-auto" style="width: 4.25rem;">Sort</div>
                                        <div class="col-auto text-end" style="width: 4.5rem;">On</div>
                                    </div>

                                    <div data-footer-items="{{ $sectionKey }}">
                                        @foreach ($section['items'] ?? [] as $index => $item)
                                            @php
                                                $menuUrlId = 'footer-url-menu-'.$sectionKey.'-'.$index;
                                                $menuUrlValue = old("menu_sections.{$sectionKey}.items.{$index}.url", $item['url'] ?? '');
                                            @endphp
                                            <fieldset class="footer-link-row" data-footer-item-row>
                                                <input type="hidden" name="menu_sections[{{ $sectionKey }}][items][{{ $index }}][item_key]" value="{{ $item['item_key'] ?? $sectionKey.'-'.$index }}">
                                                <div class="jp-form-grid jp-form-grid--filter">
                                                    <div class="col">
                                                        <label class="jp-label d-md-none">Label</label>
                                                        <input class="jp-control jp-control-sm" name="menu_sections[{{ $sectionKey }}][items][{{ $index }}][label]" value="{{ old("menu_sections.{$sectionKey}.items.{$index}.label", $item['label'] ?? '') }}">
                                                    </div>
                                                    <div class="col-auto">
                                                        <label class="jp-label d-md-none">URL</label>
                                                        <div class="d-flex align-items-center gap-1">
                                                            <input type="text" class="d-none footer-url-input" id="{{ $menuUrlId }}" name="menu_sections[{{ $sectionKey }}][items][{{ $index }}][url]" value="{{ $menuUrlValue }}" placeholder="/about-us">
                                                            <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost footer-url-btn" data-footer-url-open data-footer-url-target="#{{ $menuUrlId }}" data-footer-url-label="{{ ($section['heading'] ?? $sectionKey).' link URL' }}" title="Edit URL">
                                                                <i class="ti ti-link"></i>
                                                            </button>
                                                            <span class="footer-url-summary small text-muted" data-footer-url-summary-for="{{ $menuUrlId }}">{{ $menuUrlValue !== '' ? $menuUrlValue : 'No URL' }}</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-auto">
                                                        <label class="jp-label d-md-none">Sort</label>
                                                        <input class="jp-control jp-control-sm footer-sort-input" type="number" name="menu_sections[{{ $sectionKey }}][items][{{ $index }}][sort_order]" value="{{ old("menu_sections.{$sectionKey}.items.{$index}.sort_order", $item['sort_order'] ?? 10) }}">
                                                    </div>
                                                    <div class="col-auto text-md-end">
                                                        <div class="form-check form-switch m-0 d-inline-block">
                                                            <input class="form-check-input" type="checkbox" role="switch" name="menu_sections[{{ $sectionKey }}][items][{{ $index }}][is_enabled]" value="1" id="menu_item_{{ $sectionKey }}_{{ $index }}_enabled" @checked(old("menu_sections.{$sectionKey}.items.{$index}.is_enabled", $item['is_enabled'] ?? true))>
                                                            <label class="form-check-label" for="menu_item_{{ $sectionKey }}_{{ $index }}_enabled">On</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </fieldset>
                                        @endforeach
                                    </div>
                                    <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost mt-1" data-footer-add-item data-section="{{ $sectionKey }}">Add link</button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card mb-2">
            <div class="card-header py-2"><h3 class="jp-card__title mb-0">Get In Touch</h3></div>
            <div class="card-body row g-2">
                <div class="col-md-4">
                    <label class="jp-label">Heading</label>
                    <input class="jp-control jp-control-sm" name="contact[heading]" value="{{ old('contact.heading', $footer['contact']['heading'] ?? '') }}">
                </div>
                <div class="col-md-8">
                    <label class="jp-label">Address</label>
                    <input class="jp-control jp-control-sm" name="contact[address]" value="{{ old('contact.address', $footer['contact']['address'] ?? '') }}">
                </div>
                <div class="col-md-3">
                    <label class="jp-label">Phone</label>
                    <input class="jp-control jp-control-sm" name="contact[phone]" value="{{ old('contact.phone', $footer['contact']['phone'] ?? '') }}">
                </div>
                <div class="col-md-3">
                    <label class="jp-label">Email</label>
                    <input class="jp-control jp-control-sm" name="contact[email]" value="{{ old('contact.email', $footer['contact']['email'] ?? '') }}">
                </div>
                <div class="col-md-3">
                    <label class="jp-label">WhatsApp number</label>
                    <input class="jp-control jp-control-sm" name="contact[whatsapp]" value="{{ old('contact.whatsapp', $footer['contact']['whatsapp'] ?? '') }}">
                </div>
                <div class="col-md-3">
                    <label class="jp-label">City</label>
                    <input class="jp-control jp-control-sm" name="contact[city]" value="{{ old('contact.city', $footer['contact']['city'] ?? '') }}">
                </div>
            </div>
        </div>

        <div class="card mb-2">
            <div class="card-header py-2"><h3 class="jp-card__title mb-0">Social links</h3></div>
            <div class="card-body py-2">
                @foreach ($socialPlatforms as $platform)
                    @php
                        $row = $footer['social'][$platform] ?? ['url' => '', 'is_enabled' => false];
                        $socialUrlId = 'footer-url-social-'.$platform;
                        $socialUrlValue = old("social.{$platform}.url", $row['url'] ?? '');
                    @endphp
                    <div class="footer-social-row d-flex align-items-center flex-wrap gap-2">
                        <span class="footer-social-label text-capitalize small fw-semibold">{{ $platform }}</span>
                        <div class="d-flex align-items-center gap-1 flex-grow-1">
                            <input type="text" class="d-none footer-url-input" id="{{ $socialUrlId }}" name="social[{{ $platform }}][url]" value="{{ $socialUrlValue }}" placeholder="https://">
                            <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost footer-url-btn" data-footer-url-open data-footer-url-target="#{{ $socialUrlId }}" data-footer-url-label="{{ ucfirst($platform) }} URL" title="Edit URL">
                                <i class="ti ti-link"></i>
                            </button>
                            <span class="footer-url-summary small text-muted" data-footer-url-summary-for="{{ $socialUrlId }}">{{ $socialUrlValue !== '' ? $socialUrlValue : 'No URL' }}</span>
                        </div>
                        <div class="form-check form-switch m-0 ms-md-auto">
                            <input type="hidden" name="social[{{ $platform }}][is_enabled]" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" name="social[{{ $platform }}][is_enabled]" value="1" id="social_{{ $platform }}_enabled" @checked(old("social.{$platform}.is_enabled", $row['is_enabled'] ?? false))>
                            <label class="form-check-label" for="social_{{ $platform }}_enabled">Show on footer</label>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card mb-2">
            <div class="card-header py-2"><h3 class="jp-card__title mb-0">Bottom bar &amp; legal links</h3></div>
            <div class="jp-card__body">
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="jp-label">Copyright</label>
                        <input class="jp-control jp-control-sm" name="bottom_bar[copyright]" value="{{ old('bottom_bar.copyright', $footer['bottom_bar']['copyright'] ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="jp-label">Disclaimer</label>
                        <input class="jp-control jp-control-sm" name="bottom_bar[disclaimer]" value="{{ old('bottom_bar.disclaimer', $footer['bottom_bar']['disclaimer'] ?? '') }}">
                    </div>
                </div>

                <p class="small text-secondary mb-2">Legal / policy links</p>
                <div class="row g-2 footer-row-head mb-1 d-none d-md-flex">
                    <div class="col">Label</div>
                    <div class="col-auto" style="min-width: 9rem;">URL</div>
                    <div class="col-auto" style="width: 4.25rem;">Sort</div>
                    <div class="col-auto text-end" style="width: 4.5rem;">On</div>
                </div>
                <div data-footer-items="legal">
                    @foreach ($footer['bottom_bar']['legal_links'] ?? [] as $index => $link)
                        @php
                            $legalUrlId = 'footer-url-legal-'.$index;
                            $legalUrlValue = $link['url'] ?? '';
                        @endphp
                        <fieldset class="footer-link-row" data-footer-item-row>
                            <input type="hidden" name="bottom_bar[legal_links][{{ $index }}][item_key]" value="{{ $link['item_key'] ?? 'legal-'.$index }}">
                            <div class="jp-form-grid jp-form-grid--filter">
                                <div class="col">
                                    <label class="jp-label d-md-none">Label</label>
                                    <input class="jp-control jp-control-sm" name="bottom_bar[legal_links][{{ $index }}][label]" value="{{ $link['label'] ?? '' }}" placeholder="Label">
                                </div>
                                <div class="col-auto">
                                    <label class="jp-label d-md-none">URL</label>
                                    <div class="d-flex align-items-center gap-1">
                                        <input type="text" class="d-none footer-url-input" id="{{ $legalUrlId }}" name="bottom_bar[legal_links][{{ $index }}][url]" value="{{ $legalUrlValue }}" placeholder="/privacy-policy">
                                        <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost footer-url-btn" data-footer-url-open data-footer-url-target="#{{ $legalUrlId }}" data-footer-url-label="Legal link URL" title="Edit URL">
                                            <i class="ti ti-link"></i>
                                        </button>
                                        <span class="footer-url-summary small text-muted" data-footer-url-summary-for="{{ $legalUrlId }}">{{ $legalUrlValue !== '' ? $legalUrlValue : 'No URL' }}</span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <label class="jp-label d-md-none">Sort</label>
                                    <input class="jp-control jp-control-sm footer-sort-input" type="number" name="bottom_bar[legal_links][{{ $index }}][sort_order]" value="{{ $link['sort_order'] ?? 10 }}">
                                </div>
                                <div class="col-auto text-md-end">
                                    <div class="form-check form-switch m-0 d-inline-block">
                                        <input class="form-check-input" type="checkbox" role="switch" name="bottom_bar[legal_links][{{ $index }}][is_enabled]" value="1" id="legal_link_{{ $index }}_enabled" @checked($link['is_enabled'] ?? true)>
                                        <label class="form-check-label" for="legal_link_{{ $index }}_enabled">On</label>
                                    </div>
                                </div>
                            </div>
                        </fieldset>
                    @endforeach
                </div>
                <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost mb-3" data-footer-add-item data-section="legal">Add legal link</button>

                <p class="small text-secondary mb-2">Trust badges</p>
                <div class="row g-2 footer-row-head mb-1 d-none d-md-flex">
                    <div class="col">Label</div>
                    <div class="col-auto" style="width: 4.25rem;">Sort</div>
                    <div class="col-auto text-end" style="width: 4.5rem;">On</div>
                </div>
                @foreach ($footer['bottom_bar']['trust_badges'] ?? [] as $index => $badge)
                    <div class="footer-link-row" data-footer-item-row>
                        <input type="hidden" name="bottom_bar[trust_badges][{{ $index }}][item_key]" value="{{ $badge['item_key'] ?? 'badge-'.$index }}">
                        <div class="jp-between">
                            <div class="col">
                                <input class="jp-control jp-control-sm" name="bottom_bar[trust_badges][{{ $index }}][label]" value="{{ $badge['label'] ?? '' }}" placeholder="Badge label">
                            </div>
                            <div class="col-auto">
                                <input class="jp-control jp-control-sm footer-sort-input" type="number" name="bottom_bar[trust_badges][{{ $index }}][sort_order]" value="{{ $badge['sort_order'] ?? 10 }}">
                            </div>
                            <div class="col-auto text-md-end">
                                <div class="form-check form-switch m-0 d-inline-block">
                                    <input class="form-check-input" type="checkbox" role="switch" name="bottom_bar[trust_badges][{{ $index }}][is_enabled]" value="1" id="trust_badge_{{ $index }}_enabled" @checked($badge['is_enabled'] ?? true)>
                                    <label class="form-check-label" for="trust_badge_{{ $index }}_enabled">On</label>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card mb-2">
            <div class="card-header py-2"><h3 class="jp-card__title mb-0">Style</h3></div>
            <div class="card-body row g-2">
                @foreach ([
                    'background_color' => ['label' => 'Background', 'fallback' => '#F8FAFC'],
                    'bottom_bar_background_color' => ['label' => 'Bottom bar', 'fallback' => '#F1F5F9'],
                    'text_color' => ['label' => 'Text', 'fallback' => '#334155'],
                    'heading_color' => ['label' => 'Headings', 'fallback' => '#0F172A'],
                    'link_color' => ['label' => 'Links', 'fallback' => '#1E3A5F'],
                    'link_hover_color' => ['label' => 'Link hover', 'fallback' => '#0C4A6E'],
                    'accent_color' => ['label' => 'Accent', 'fallback' => '#0284C7'],
                ] as $key => $field)
                    @php
                        $rawHex = old("style.{$key}", $footer['style'][$key] ?? '');
                        $hex = is_string($rawHex) && preg_match('/^#[0-9A-Fa-f]{6}$/', $rawHex) ? $rawHex : $field['fallback'];
                        $pickerId = 'footer_style_'.$key;
                        $previewId = $pickerId.'_preview';
                    @endphp
                    <div class="col-lg-3 col-md-4">
                        <label class="jp-label" for="{{ $pickerId }}">{{ $field['label'] }}</label>
                        <div class="d-flex align-items-center gap-2">
                            <input
                                type="color"
                                class="jp-control jp-control-color flex-shrink-0"
                                id="{{ $pickerId }}"
                                name="style[{{ $key }}]"
                                value="{{ $hex }}"
                                data-brand-color-picker
                                data-brand-color-preview="{{ $previewId }}"
                                title="Choose {{ strtolower($field['label']) }} color"
                            >
                            <input
                                type="text"
                                class="jp-control jp-control-sm font-monospace"
                                id="{{ $previewId }}"
                                value="{{ $hex }}"
                                readonly
                                tabindex="-1"
                                aria-label="{{ $field['label'] }} hex value"
                            >
                        </div>
                    </div>
                @endforeach
                <div class="col-lg-3 col-md-4">
                    <label class="jp-label">Spacing</label>
                    <select class="jp-control jp-control-sm" name="style[spacing]">
                        @foreach ($spacingOptions as $opt)
                            <option value="{{ $opt }}" @selected(old('style.spacing', $footer['style']['spacing'] ?? 'normal') === $opt)>{{ ucfirst($opt) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 col-md-4">
                    <label class="jp-label">Columns (desktop)</label>
                    <select class="jp-control jp-control-sm" name="style[columns]">
                        <option value="5" @selected((int) old('style.columns', $footer['style']['columns'] ?? 5) === 5)>5 columns</option>
                        <option value="4" @selected((int) old('style.columns', $footer['style']['columns'] ?? 5) === 4)>4 columns</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="footer-action-bar rounded-bottom px-3 py-3 d-flex justify-content-end">
            <button type="submit" class="jp-btn jp-btn--primary">Save footer</button>
        </div>
    </form>

    <div class="modal fade" id="footerUrlModal" tabindex="-1" aria-labelledby="footerUrlModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="footerUrlModalTitle">Edit URL</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="jp-label" for="footerUrlModalInput" id="footerUrlModalLabel">URL</label>
                    <input type="text" class="jp-control" id="footerUrlModalInput" autocomplete="off">
                    <div class="form-text">Use a site path like <code>/privacy-policy</code> or a full <code>https://</code> URL for social links.</div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="jp-btn jp-btn--ghost btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="jp-btn jp-btn--primary btn-sm" id="footerUrlModalSave">Save URL</button>
                </div>
            </div>
        </div>
    </div>

    <template id="footer-item-template-menu">
        <fieldset class="footer-link-row" data-footer-item-row>
            <div class="jp-form-grid jp-form-grid--filter">
                <div class="col">
                    <label class="jp-label d-md-none">Label</label>
                    <input class="jp-control jp-control-sm" name="menu_sections[__SECTION__][items][__INDEX__][label]">
                </div>
                <div class="col-auto">
                    <label class="jp-label d-md-none">URL</label>
                    <div class="d-flex align-items-center gap-1">
                        <input type="text" class="d-none footer-url-input" id="footer-url-menu-__SECTION__-__INDEX__" name="menu_sections[__SECTION__][items][__INDEX__][url]" value="" placeholder="/your-page">
                        <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost footer-url-btn" data-footer-url-open data-footer-url-target="#footer-url-menu-__SECTION__-__INDEX__" data-footer-url-label="Menu link URL" title="Edit URL">
                            <i class="ti ti-link"></i>
                        </button>
                        <span class="footer-url-summary small text-muted" data-footer-url-summary-for="footer-url-menu-__SECTION__-__INDEX__">No URL</span>
                    </div>
                </div>
                <div class="col-auto">
                    <label class="jp-label d-md-none">Sort</label>
                    <input class="jp-control jp-control-sm footer-sort-input" type="number" name="menu_sections[__SECTION__][items][__INDEX__][sort_order]" value="100">
                </div>
                <div class="col-auto text-md-end">
                    <div class="form-check form-switch m-0 d-inline-block">
                        <input class="form-check-input" type="checkbox" role="switch" name="menu_sections[__SECTION__][items][__INDEX__][is_enabled]" value="1" id="menu_item___SECTION____INDEX___enabled" checked>
                        <label class="form-check-label" for="menu_item___SECTION____INDEX___enabled">On</label>
                    </div>
                </div>
            </div>
        </fieldset>
    </template>
    <template id="footer-item-template-legal">
        <fieldset class="footer-link-row" data-footer-item-row>
            <div class="jp-form-grid jp-form-grid--filter">
                <div class="col">
                    <label class="jp-label d-md-none">Label</label>
                    <input class="jp-control jp-control-sm" name="bottom_bar[legal_links][__INDEX__][label]" placeholder="Privacy Policy">
                </div>
                <div class="col-auto">
                    <label class="jp-label d-md-none">URL</label>
                    <div class="d-flex align-items-center gap-1">
                        <input type="text" class="d-none footer-url-input" id="footer-url-legal-__INDEX__" name="bottom_bar[legal_links][__INDEX__][url]" value="" placeholder="/privacy-policy">
                        <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost footer-url-btn" data-footer-url-open data-footer-url-target="#footer-url-legal-__INDEX__" data-footer-url-label="Legal link URL" title="Edit URL">
                            <i class="ti ti-link"></i>
                        </button>
                        <span class="footer-url-summary small text-muted" data-footer-url-summary-for="footer-url-legal-__INDEX__">No URL</span>
                    </div>
                </div>
                <div class="col-auto">
                    <label class="jp-label d-md-none">Sort</label>
                    <input class="jp-control jp-control-sm footer-sort-input" type="number" name="bottom_bar[legal_links][__INDEX__][sort_order]" value="100">
                </div>
                <div class="col-auto text-md-end">
                    <div class="form-check form-switch m-0 d-inline-block">
                        <input class="form-check-input" type="checkbox" role="switch" name="bottom_bar[legal_links][__INDEX__][is_enabled]" value="1" id="legal_link___INDEX___enabled" checked>
                        <label class="form-check-label" for="legal_link___INDEX___enabled">On</label>
                    </div>
                </div>
            </div>
        </fieldset>
    </template>

    <script>
        (function () {
            var footerUrlModalEl = document.getElementById('footerUrlModal');
            var footerUrlModal = footerUrlModalEl && window.bootstrap ? new window.bootstrap.Modal(footerUrlModalEl) : null;
            var footerUrlModalInput = document.getElementById('footerUrlModalInput');
            var footerUrlModalTitle = document.getElementById('footerUrlModalTitle');
            var footerUrlModalLabel = document.getElementById('footerUrlModalLabel');
            var footerUrlActiveTarget = null;

            function updateFooterUrlSummary(input) {
                if (!input || !input.id) {
                    return;
                }
                var value = (input.value || '').trim();
                document.querySelectorAll('[data-footer-url-summary-for="' + input.id + '"]').forEach(function (el) {
                    el.textContent = value !== '' ? value : 'No URL';
                    el.classList.toggle('text-muted', value === '');
                    el.classList.toggle('text-body', value !== '');
                });
                document.querySelectorAll('[data-footer-url-target="#' + input.id + '"]').forEach(function (btn) {
                    btn.classList.toggle('btn-outline-primary', value !== '');
                    btn.classList.toggle('btn-outline-secondary', value === '');
                });
            }

            function wireFooterUrlControls(root) {
                var scope = root || document;
                scope.querySelectorAll('.footer-url-input').forEach(function (input) {
                    updateFooterUrlSummary(input);
                });
                scope.querySelectorAll('[data-footer-url-open]').forEach(function (btn) {
                    if (btn.dataset.footerUrlWired === '1') {
                        return;
                    }
                    btn.dataset.footerUrlWired = '1';
                    btn.addEventListener('click', function () {
                        var selector = btn.getAttribute('data-footer-url-target') || '';
                        var input = selector ? document.querySelector(selector) : null;
                        if (!input || !footerUrlModal) {
                            return;
                        }
                        footerUrlActiveTarget = input;
                        footerUrlModalInput.value = input.value || '';
                        footerUrlModalTitle.textContent = btn.getAttribute('data-footer-url-label') || 'Edit URL';
                        footerUrlModalLabel.textContent = input.getAttribute('placeholder') || 'URL';
                        footerUrlModalInput.placeholder = input.getAttribute('placeholder') || '';
                        footerUrlModal.show();
                    });
                });
            }

            var footerUrlModalSave = document.getElementById('footerUrlModalSave');
            if (footerUrlModalSave) {
                footerUrlModalSave.addEventListener('click', function () {
                    if (footerUrlActiveTarget) {
                        footerUrlActiveTarget.value = footerUrlModalInput.value;
                        updateFooterUrlSummary(footerUrlActiveTarget);
                    }
                    if (footerUrlModal) {
                        footerUrlModal.hide();
                    }
                });
            }

            wireFooterUrlControls(document);

            document.querySelectorAll('[data-footer-add-item]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var section = btn.getAttribute('data-section');
                    var container = document.querySelector('[data-footer-items="' + section + '"]');
                    var index = container.querySelectorAll('[data-footer-item-row]').length;
                    var tplId = section === 'legal' ? 'footer-item-template-legal' : 'footer-item-template-menu';
                    var html = document.getElementById(tplId).innerHTML
                        .replace(/__SECTION__/g, section)
                        .replace(/__INDEX__/g, String(index));
                    container.insertAdjacentHTML('beforeend', html);
                    var row = container.lastElementChild;
                    if (row) {
                        wireFooterUrlControls(row);
                    }
                });
            });

            document.querySelectorAll('[data-brand-color-picker]').forEach(function (picker) {
                var preview = document.getElementById(picker.getAttribute('data-brand-color-preview') || '');
                var sync = function () {
                    if (preview) {
                        preview.value = picker.value;
                    }
                };
                picker.addEventListener('input', sync);
                picker.addEventListener('change', sync);
                sync();
            });
        })();
    </script>
@endsection
