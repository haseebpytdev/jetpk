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

<article class="ota-account-traveler-card ota-account-traveler-card--default" data-testid="default-traveler-card">
    <div class="ota-account-traveler-card__head">
        <div>
            <h2 class="ota-account-traveler-card__name">{{ $fullName ?: 'Your profile' }}</h2>
            <p class="ota-account-traveler-card__meta">Default traveler · from your account profile</p>
        </div>
        <div class="ota-account-traveler-card__badges">
            <span class="ota-account-badge ota-account-badge--info">Default</span>
            @unless ($isComplete)
                <span class="ota-account-badge ota-account-badge--warning" data-testid="default-traveler-incomplete">Incomplete</span>
            @endunless
        </div>
    </div>
    <dl class="ota-account-traveler-card__details">
        <div><dt>Nationality</dt><dd>{{ $nationality ?? '—' }}</dd></div>
        @if ($isSaved && $traveler->maskedDocumentNumber())
            <div><dt>Document</dt><dd data-testid="default-traveler-masked-doc">{{ $traveler->maskedDocumentNumber() }}</dd></div>
        @endif
        <div><dt>Document expiry</dt><dd>
            @if ($isSaved)
                <x-dashboard.status-badge :status="$expiryStatus" />
            @else
                {{ $docExpiry?->format('j M Y') ?? '—' }}
            @endif
        </dd></div>
        <div><dt>Email</dt><dd>{{ $isSaved ? ($traveler->email ?? '—') : ($card['email'] ?? '—') }}</dd></div>
    </dl>
    @unless ($isComplete)
        <p class="ota-account-traveler-card__help">Complete this traveler to speed up checkout.</p>
    @endunless
    <div class="ota-account-traveler-card__actions">
        @if ($isSaved)
            <a href="{{ route($routePrefix.'.edit', $traveler) }}" class="ota-account-btn ota-account-btn--primary ota-account-btn--sm">Edit traveler</a>
        @else
            <a href="{{ route('profile.edit') }}" class="ota-account-btn ota-account-btn--primary ota-account-btn--sm" data-testid="default-traveler-complete-profile">Complete profile</a>
        @endif
    </div>
</article>
