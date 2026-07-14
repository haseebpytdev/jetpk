<div class="jp-card">
    <h2 class="jp-card__title">Footer intro</h2>
    <label class="jp-label">Description</label>
    <textarea class="jp-input" rows="3" name="content[description][text]">{{ data_get($content, 'description.text') }}</textarea>
</div>

<div class="jp-card">
    <h2 class="jp-card__title">Legal</h2>
    <label class="jp-label">Copyright line</label>
    <input class="jp-input" name="content[legal][copyright]" value="{{ data_get($content, 'legal.copyright') }}">
    <label class="jp-label">Company line</label>
    <input class="jp-input" name="content[legal][company_line]" value="{{ data_get($content, 'legal.company_line') }}">
</div>

<div class="jp-card">
    <h2 class="jp-card__title">Social links</h2>
    @foreach (range(0, 3) as $i)
        <div class="jp-form-grid" style="margin-bottom:8px;">
            <div><label class="jp-label">Platform</label><input class="jp-input" name="content[social][{{ $i }}][platform]" value="{{ data_get($content, "social.{$i}.platform") }}"></div>
            <div><label class="jp-label">URL</label><input class="jp-input" name="content[social][{{ $i }}][url]" value="{{ data_get($content, "social.{$i}.url") }}"></div>
        </div>
    @endforeach
</div>
