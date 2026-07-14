@extends(client_layout('dashboard', 'admin'))

@section('title', 'Notification routing')

@section('page-header')
    <div class="jp-between">
        <div>
            <p class="jp-backlink"><a href="{{ client_route('admin.settings.communications.index') }}">← Communications</a></p>
            <h1>Notification routing</h1>
            <p>Per-event delivery by category — enable, audience scope, and optional To/CC/BCC overrides.</p>
        </div>
        <div class="jp-toolbar">
            <span class="jp-badge jp-badge--warn jp-is-hidden" data-jp-unsaved-badge>Unsaved changes</span>
            <a href="{{ client_route('admin.settings.communications.delivery-log.index') }}" class="jp-btn jp-btn--sm jp-btn--ghost">Delivery log</a>
            <a href="{{ client_route('admin.settings.communications.templates.index') }}" class="jp-btn jp-btn--sm jp-btn--outline">Email templates</a>
        </div>
    </div>
@endsection

@section('content')
    @include('themes.admin.jetpakistan.partials.flash')

    @php
        $totalEvents = collect($eventGroups)->flatten()->count();
    @endphp

    @if ($canUpdate)
        <form method="post" action="{{ client_route('admin.settings.communications.notification-events.update') }}" class="jp-stack" data-jp-unsaved-form id="jp-notification-routing-form">
            @csrf
            @method('PATCH')

            <div class="jp-notification-toolbar jp-between">
                <div class="jp-toolbar">
                    <input type="search" class="jp-control" placeholder="Search events…" data-jp-notification-search style="max-width:280px;">
                    <select class="jp-control" data-jp-bulk-audience style="max-width:160px;">
                        <option value="">Bulk audience…</option>
                        @foreach (['admin', 'staff', 'agent', 'customer'] as $scope)
                            <option value="{{ $scope }}">{{ ucfirst($scope) }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost" data-jp-bulk-audience-apply>Apply to visible</button>
                </div>
                <div class="jp-toolbar">
                    <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost" data-jp-bulk-enable="1">Enable visible</button>
                    <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost" data-jp-bulk-enable="0">Disable visible</button>
                </div>
            </div>

            <nav class="jp-page-editor__nav" aria-label="Event categories" data-jp-notification-category-tabs>
                <button type="button" class="jp-queue-tab is-active" data-jp-notification-tab="all">All ({{ $totalEvents }})</button>
                @foreach ($eventGroups as $category => $categoryEvents)
                    <button type="button" class="jp-queue-tab" data-jp-notification-tab="{{ \Illuminate\Support\Str::slug($category) }}">{{ $category }} ({{ count($categoryEvents) }})</button>
                @endforeach
            </nav>

            @foreach ($eventGroups as $category => $categoryEvents)
                @php
                    $categorySlug = \Illuminate\Support\Str::slug($category);
                @endphp
                <details class="jp-card jp-notification-category" data-jp-notification-category="{{ $categorySlug }}" open>
                    <summary class="jp-between jp-notification-category__head">
                        <h2 class="jp-card__title">{{ $category }} <span class="jp-muted">({{ count($categoryEvents) }})</span></h2>
                        <div class="jp-toolbar" onclick="event.stopPropagation()">
                            <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost" data-jp-category-enable="1" data-category="{{ $categorySlug }}">Enable category</button>
                            <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost" data-jp-category-enable="0" data-category="{{ $categorySlug }}">Disable category</button>
                            <button type="submit" class="jp-btn jp-btn--sm jp-btn--primary" data-jp-save-category="{{ $categorySlug }}">Save category</button>
                        </div>
                    </summary>
                    <div class="jp-notification-rows">
                        @foreach ($categoryEvents as $event)
                            @php
                                $row = $notificationSettings[$event->value] ?? null;
                                $toOld = old('events.'.$event->value.'.recipient_emails');
                                $toVal = $toOld !== null ? $toOld : implode(', ', $row?->recipient_emails ?? []);
                                $ccOld = old('events.'.$event->value.'.cc_emails');
                                $ccVal = $ccOld !== null ? $ccOld : implode(', ', $row?->cc_emails ?? []);
                                $bccOld = old('events.'.$event->value.'.bcc_emails');
                                $bccVal = $bccOld !== null ? $bccOld : implode(', ', $row?->bcc_emails ?? []);
                                $enabledOld = old('events.'.$event->value.'.enabled');
                                $enabledVal = $enabledOld !== null ? filter_var($enabledOld, FILTER_VALIDATE_BOOLEAN) : (bool) ($row?->enabled ?? true);
                                $scopeOld = old('events.'.$event->value.'.recipient_scope');
                                $scopeVal = $scopeOld ?? ($row?->recipient_scope ?? $event->defaultScope());
                            @endphp
                            <details class="jp-notification-row" data-jp-notification-row data-event-label="{{ $event->value }}" data-category="{{ $categorySlug }}">
                                <summary class="jp-notification-row__summary">
                                    <strong>{{ str_replace('_', ' ', $event->value) }}</strong>
                                    <span class="jp-muted">{{ $event->defaultScope() }}</span>
                                    <span class="jp-badge jp-badge--{{ $enabledVal ? 'success' : 'muted' }}">{{ $enabledVal ? 'On' : 'Off' }}</span>
                                </summary>
                                <div class="jp-notification-row__body">
                                    <label class="jp-check">
                                        <input type="hidden" name="events[{{ $event->value }}][enabled]" value="0">
                                        <input type="checkbox" name="events[{{ $event->value }}][enabled]" value="1" @checked($enabledVal) data-jp-event-enabled>
                                        <span>Enabled</span>
                                    </label>
                                    <label class="jp-label">Audience</label>
                                    <select class="jp-control" name="events[{{ $event->value }}][recipient_scope]" required data-jp-event-scope>
                                        @foreach (['admin', 'staff', 'agent', 'customer'] as $scope)
                                            <option value="{{ $scope }}" @selected($scopeVal === $scope)>{{ ucfirst($scope) }}</option>
                                        @endforeach
                                    </select>
                                    <details class="jp-notification-advanced">
                                        <summary class="jp-muted">Advanced To / CC / BCC</summary>
                                        <label class="jp-label">To override</label>
                                        <textarea class="jp-control jp-control--textarea" rows="2" name="events[{{ $event->value }}][recipient_emails]" placeholder="Comma-separated emails">{{ $toVal }}</textarea>
                                        <label class="jp-label">CC</label>
                                        <textarea class="jp-control jp-control--textarea" rows="2" name="events[{{ $event->value }}][cc_emails]" placeholder="Comma-separated emails">{{ $ccVal }}</textarea>
                                        <label class="jp-label">BCC</label>
                                        <textarea class="jp-control jp-control--textarea" rows="2" name="events[{{ $event->value }}][bcc_emails]" placeholder="Comma-separated emails">{{ $bccVal }}</textarea>
                                    </details>
                                </div>
                            </details>
                        @endforeach
                    </div>
                </details>
            @endforeach

            <div class="jp-action-bar">
                <button type="submit" class="jp-btn jp-btn--primary">Save all routing</button>
            </div>
        </form>
    @else
        <div class="jp-alert jp-alert--warn">You do not have permission to update notification routing.</div>
    @endif
@endsection

@push('scripts')
<script>
(() => {
  const form = document.getElementById('jp-notification-routing-form');
  const unsaved = document.querySelector('[data-jp-unsaved-badge]');
  const markDirty = () => unsaved?.classList.remove('jp-is-hidden');
  form?.addEventListener('input', markDirty);
  form?.addEventListener('change', markDirty);

  document.querySelector('[data-jp-notification-search]')?.addEventListener('input', (e) => {
    const q = e.target.value.toLowerCase();
    document.querySelectorAll('[data-jp-notification-row]').forEach((row) => {
      const label = (row.getAttribute('data-event-label') || '').toLowerCase();
      row.style.display = label.includes(q) ? '' : 'none';
    });
  });

  document.querySelectorAll('[data-jp-bulk-enable]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const on = btn.getAttribute('data-jp-bulk-enable') === '1';
      document.querySelectorAll('[data-jp-notification-row]').forEach((row) => {
        if (row.style.display === 'none') return;
        const cb = row.querySelector('[data-jp-event-enabled]');
        if (cb) { cb.checked = on; markDirty(); }
      });
    });
  });

  document.querySelector('[data-jp-bulk-audience-apply]')?.addEventListener('click', () => {
    const scope = document.querySelector('[data-jp-bulk-audience]')?.value;
    if (!scope) return;
    document.querySelectorAll('[data-jp-notification-row]').forEach((row) => {
      if (row.style.display === 'none') return;
      const sel = row.querySelector('[data-jp-event-scope]');
      if (sel) { sel.value = scope; markDirty(); }
    });
  });

  document.querySelectorAll('[data-jp-category-enable]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const on = btn.getAttribute('data-jp-category-enable') === '1';
      const cat = btn.getAttribute('data-category');
      document.querySelectorAll(`[data-jp-notification-row][data-category="${cat}"]`).forEach((row) => {
        const cb = row.querySelector('[data-jp-event-enabled]');
        if (cb) { cb.checked = on; markDirty(); }
      });
    });
  });

  document.querySelectorAll('[data-jp-notification-tab]').forEach((tab) => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('[data-jp-notification-tab]').forEach((t) => t.classList.remove('is-active'));
      tab.classList.add('is-active');
      const key = tab.getAttribute('data-jp-notification-tab');
      document.querySelectorAll('[data-jp-notification-category]').forEach((section) => {
        if (key === 'all') {
          section.style.display = '';
          return;
        }
        section.style.display = section.getAttribute('data-jp-notification-category') === key ? '' : 'none';
      });
    });
  });
})();
</script>
@endpush
