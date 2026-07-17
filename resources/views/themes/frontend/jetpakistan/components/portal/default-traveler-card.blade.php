{{-- JP-PORTAL-3 · JetPK default traveler card (Customer only)
     Replaces dashboard.customer.partials.default-traveler-card on JetPK-resolved pages.

     REQUIRED because the legacy partial is built entirely from `ota-account-*` classes, which
     Task 13 forbids in any JetPK-resolved page. The legacy partial REMAINS on disk untouched and
     serves legacy dashboard clients when standalone mode is off\.

     Preserved verbatim from dashboard.customer.partials.default-traveler-card:
       • $defaultTraveler['source'] === 'saved' vs 'profile' branching
       • $defaultTraveler['traveler'] / $defaultTraveler['card'] shapes
       • fullName() vs card['full_name'] ?? first_name.' '.last_name fallback
       • isComplete() vs (bool) card['is_complete']
       • nationality, document_expiry, email resolution per branch
       • expiryStatus: documentExpiryStatus() when saved, else ($docExpiry ? 'valid' : 'missing')
       • <x-dashboard.status-badge> for the saved branch ONLY (canonical — reused, not duplicated)
       • profile branch renders $docExpiry?->format('j M Y') ?? '—' (NOT a badge)
       • maskedDocumentNumber() rendered only when saved and non-null
       • routes: $routePrefix.'.edit' (saved) / profile.edit (profile)
       • data-testids: default-traveler-card, default-traveler-incomplete,
         default-traveler-masked-doc, default-traveler-complete-profile
     Local $traveler shadowing is retained exactly as legacy does it.
--}}
@php
    $isSaved = ($defaultTraveler['source'] ?? '') === 'saved';
    $traveler = $isSaved ? $defaultTraveler['traveler'] : null;
    $card = $isSaved ? null : ($defaultTraveler['card'] ?? []);
    $fullName = $isSaved ? $traveler->fullName() : ($card['full_name'] ?? trim(($card['first_name'] ?? '').' '.($card['last_name'] ?? '')));
    $isComplete = $isSaved ? $traveler->isComplete() : (bool) ($card['is_complete'] ?? false);
    $nationality = $isSaved ? $traveler->nationality : ($card['nationality'] ?? null);
    $docExpiry = $isSaved ? $traveler->document_expiry : ($card['document_expiry'] ?? null);
    $expiryStatus = $isSaved ? $traveler->documentExpiryStatus() : ($docExpiry ? 'valid' : 'missing');
@endphp

<article class="jp-portal__list-card jp-portal__list-card--default" data-testid="default-traveler-card">
    <div class="jp-portal__list-card-head">
        <div>
            <h2 class="jp-portal__list-card-name">{{ $fullName ?: 'Your profile' }}</h2>
            <p class="jp-portal__list-card-meta">Default traveler · from your account profile</p>
        </div>
        <div class="jp-portal__list-card-badges">
            <span class="jp-badge jp-badge--info">Default</span>
            @unless ($isComplete)
                <span class="jp-badge jp-badge--warning" data-testid="default-traveler-incomplete">Incomplete</span>
            @endunless
        </div>
    </div>

    <dl class="jp-portal__details">
        <div><dt>Nationality</dt><dd>{{ $nationality ?? '—' }}</dd></div>
        @if ($isSaved && $traveler->maskedDocumentNumber())
            <div><dt>Document</dt><dd data-testid="default-traveler-masked-doc">{{ $traveler->maskedDocumentNumber() }}</dd></div>
        @endif
        <div>
            <dt>Document expiry</dt>
            <dd>
                @if ($isSaved)
                    <x-dashboard.status-badge :status="$expiryStatus" />
                @else
                    {{ $docExpiry?->format('j M Y') ?? '—' }}
                @endif
            </dd>
        </div>
        <div><dt>Email</dt><dd>{{ $isSaved ? ($traveler->email ?? '—') : ($card['email'] ?? '—') }}</dd></div>
    </dl>

    @unless ($isComplete)
        <p class="jp-portal__list-card-help">Complete this traveler to speed up checkout.</p>
    @endunless

    <div class="jp-portal__list-card-actions">
        @if ($isSaved)
            <a href="{{ route($routePrefix.'.edit', $traveler) }}" class="jp-btn jp-btn--primary jp-btn--sm">Edit traveler</a>
        @else
            <a href="{{ route('profile.edit') }}" class="jp-btn jp-btn--primary jp-btn--sm" data-testid="default-traveler-complete-profile">Complete profile</a>
        @endif
    </div>
</article>
