@props([
    'ticket',
])

@php
    $status = $ticket->status instanceof \App\Enums\SupportTicketStatus
        ? $ticket->status
        : \App\Enums\SupportTicketStatus::tryFrom((string) $ticket->status);
    $statusLabel = $status?->customerLabel() ?? str_replace('_', ' ', (string) $ticket->status);
    $statusTone = match ($status) {
        \App\Enums\SupportTicketStatus::Open, \App\Enums\SupportTicketStatus::Pending => 'pending',
        \App\Enums\SupportTicketStatus::Resolved, \App\Enums\SupportTicketStatus::Closed => 'positive',
        default => 'muted',
    };
@endphp

<article class="ota-mobile-customer__card ota-mobile-customer__ticket-card" data-testid="ota-mobile-customer-support-ticket-card">
    <div class="ota-mobile-customer__card-head">
        <span class="ota-mobile-customer__ref">#{{ $ticket->id }}</span>
        <span class="ota-mobile-customer__pill ota-mobile-customer__pill--{{ $statusTone }}">{{ $statusLabel }}</span>
    </div>
    <p class="ota-mobile-customer__ticket-subject">{{ e($ticket->subject) }}</p>
    @if ($ticket->booking)
        <p class="ota-mobile-customer__ticket-meta">Booking: {{ e($ticket->booking->booking_reference) }}</p>
    @endif
    <p class="ota-mobile-customer__ticket-meta">
        Updated {{ $ticket->last_reply_at?->diffForHumans() ?? $ticket->created_at->diffForHumans() }}
    </p>
    <div class="ota-mobile-customer__actions">
        <a href="{{ route('customer.support.tickets.show', $ticket) }}" class="ota-mobile-customer__btn ota-mobile-customer__btn--primary">View</a>
    </div>
</article>
