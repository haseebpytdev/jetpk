@php
    use App\Support\Client\ClientPageKeys;
@endphp
<div class="jp-card">
    <h2 class="jp-card__title">Page content</h2>
    <label class="jp-label">Title</label>
    <input aria-label="Title" class="jp-input" name="content[content][title]" value="{{ data_get($content, 'content.title') }}">
    <label class="jp-label">Intro</label>
    <textarea aria-label="Intro" class="jp-input" rows="2" name="content[content][intro]">{{ data_get($content, 'content.intro') }}</textarea>
    <label class="jp-label">Body</label>
    <textarea aria-label="Body" class="jp-input" rows="10" name="content[content][body]">{{ data_get($content, 'content.body') }}</textarea>
    <p class="jp-muted">Plain text only — no HTML or scripts. Line breaks are preserved.</p>
</div>
