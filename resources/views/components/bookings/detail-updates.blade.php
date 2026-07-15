@props([
    'booking',
    'safeCommEvents' => [],
    'shell' => 'account',
])

@php $isAccount = $shell === 'account'; @endphp

<div class="{{ $isAccount ? 'ota-account-card' : 'card border-0 shadow-sm' }}">
    <div class="{{ $isAccount ? 'ota-account-card__head' : 'card-header border-0' }}">
        <h3 class="{{ $isAccount ? 'ota-account-card__title' : 'card-title mb-0' }}">Updates</h3>
    </div>
    <div class="{{ $isAccount ? 'ota-account-card__body' : 'card-body' }}">
        @forelse($booking->communicationLogs->where('channel', 'email')->whereIn('event', $safeCommEvents)->sortByDesc('created_at') as $log)
            <div class="small mb-2">
                <x-time.local :value="$log->created_at" context="public" :show-utc="false" /> —
                {{ str_replace('_', ' ', $log->event) }}
            </div>
        @empty
            <div class="ota-booking-detail-updates-empty text-secondary small">
                <i class="ti ti-calendar-event" aria-hidden="true"></i>
                <span>No customer-facing updates yet.</span>
            </div>
        @endforelse
    </div>
</div>
