<div class="jp-card">
    <h2 class="jp-card__title">Announcement banner</h2>
    <label class="jp-check" style="margin-bottom:10px;">
        <input type="hidden" name="content[announcement][enabled]" value="0">
        <input type="checkbox" name="content[announcement][enabled]" value="1" @checked(data_get($content, 'announcement.enabled') == '1')>
        <span>Show announcement on public site</span>
    </label>
    <label class="jp-label">Message</label>
    <input class="jp-input" name="content[announcement][text]" value="{{ data_get($content, 'announcement.text') }}">
    <label class="jp-label">Link (optional)</label>
    <input class="jp-input" name="content[announcement][link]" value="{{ data_get($content, 'announcement.link') }}">
</div>

<div class="jp-card">
    <h2 class="jp-card__title">Header support</h2>
    <div class="jp-form-grid">
        <div><label class="jp-label">Phone</label><input class="jp-input" name="content[header_support][phone]" value="{{ data_get($content, 'header_support.phone') }}"></div>
        <div><label class="jp-label">Email</label><input class="jp-input" name="content[header_support][email]" value="{{ data_get($content, 'header_support.email') }}"></div>
        <div><label class="jp-label">Hours</label><input class="jp-input" name="content[header_support][hours]" value="{{ data_get($content, 'header_support.hours') }}"></div>
    </div>
</div>

<div class="jp-card">
    <h2 class="jp-card__title">Default SEO</h2>
    <label class="jp-label">Title</label>
    <input class="jp-input" name="content[seo][title]" value="{{ data_get($content, 'seo.title') }}">
    <label class="jp-label">Description</label>
    <textarea class="jp-input" rows="2" name="content[seo][description]">{{ data_get($content, 'seo.description') }}</textarea>
    <label class="jp-label">OG image asset key</label>
    <input class="jp-input" name="content[seo][og_image]" value="{{ data_get($content, 'seo.og_image') }}" placeholder="og_image">
</div>
