@php
    $brandingUrl = Route::has('admin.settings.branding.edit')
        ? route('admin.settings.branding.edit')
        : null;
@endphp
<div class="jp-card jp-branding-ownership">
    <h2 class="jp-card__title">Logo &amp; brand identity</h2>
    <p class="jp-help">Primary logo, favicon, and default share image are managed in <strong>Settings → Branding</strong>. Page Settings only covers page-specific hero and section imagery.</p>
    @if ($brandingUrl)
        <p><a href="{{ $brandingUrl }}" class="jp-btn jp-btn--sm jp-btn--ghost">Open Branding settings</a></p>
    @endif
    <ul class="jp-list-plain jp-muted" style="margin-top:12px;font-size:13px;">
        <li>Header / footer logo → Branding settings</li>
        <li>Favicon → Branding settings</li>
        <li>Media Library → reusable general assets only</li>
        <li>Page hero images → Media tab on each page</li>
    </ul>
</div>
