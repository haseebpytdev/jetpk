{{--
  Permission-denied / access-denied state (new — Phase 0 gap P-1).
  Phase 1 · JETPK-DASHBOARD-UI-FOUNDATION

  Canonical denied UX shared by Agent Staff deep-links, module-disabled surfaces,
  and @cannot branches. `returnHref` should be produced with client_route().
--}}
@props([
    'title' => 'You don’t have access to this page',
    'message' => 'Your account doesn’t have permission to view this section. If you think this is a mistake, contact your administrator.',
    'reason' => null,
    'icon' => 'ti-lock',
    'returnHref' => null,
    'returnLabel' => 'Back to dashboard',
])

<div {{ $attributes->class(['ota-dashboard-denied']) }} role="alert" data-testid="permission-denied">
    <span class="ota-dashboard-denied__icon"><i class="ti {{ $icon }}" aria-hidden="true"></i></span>
    <h2 class="ota-dashboard-denied__title">{{ $title }}</h2>
    <p class="ota-dashboard-denied__text">{{ $message }}</p>

    @if ($reason !== null && trim((string) $reason) !== '')
        <p class="ota-dashboard-denied__reason">{{ $reason }}</p>
    @endif

    @isset($actions)
        <div class="ota-dashboard-denied__actions">{{ $actions }}</div>
    @elseif ($returnHref !== null)
        <div class="ota-dashboard-denied__actions">
            <a href="{{ $returnHref }}" class="ota-account-btn ota-account-btn--primary">{{ $returnLabel }}</a>
        </div>
    @endisset
</div>
