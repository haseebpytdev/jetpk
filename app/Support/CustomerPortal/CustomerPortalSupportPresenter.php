<?php

namespace App\Support\CustomerPortal;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketMessageVisibility;
use App\Models\Booking;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Customer support ticket JSON for Next.js dashboard.
 */
class CustomerPortalSupportPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function presentIndex(LengthAwarePaginator $tickets): array
    {
        return [
            'ok' => true,
            'tickets' => collect($tickets->items())
                ->map(fn (SupportTicket $ticket) => $this->presentListItem($ticket))
                ->values()
                ->all(),
            'pagination' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
                'from' => $tickets->firstItem(),
                'to' => $tickets->lastItem(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentListItem(SupportTicket $ticket): array
    {
        return [
            'reference' => $ticket->ticket_reference,
            'subject' => $ticket->subject,
            'category' => (string) ($ticket->category?->value ?? $ticket->category),
            'category_label' => ucfirst(str_replace('_', ' ', (string) ($ticket->category?->value ?? $ticket->category))),
            'booking_reference' => $ticket->booking?->display_reference,
            'status' => [
                'code' => (string) ($ticket->status?->value ?? $ticket->status),
                'label' => ucfirst(str_replace('_', ' ', (string) ($ticket->status?->value ?? $ticket->status))),
            ],
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => ($ticket->last_reply_at ?? $ticket->updated_at)?->toIso8601String(),
            'detail_url' => '/customer/support/'.$ticket->ticket_reference,
            'can_reply' => ! $ticket->isClosed(),
            'can_close' => ! $ticket->isClosed(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentDetail(SupportTicket $ticket): array
    {
        $ticket->loadMissing([
            'booking',
            'messages' => fn ($q) => $q
                ->where('visibility', SupportTicketMessageVisibility::CustomerVisible)
                ->with('author'),
        ]);

        return [
            'ok' => true,
            'ticket' => $this->presentListItem($ticket),
            'conversation' => $ticket->messages
                ->map(fn ($message) => [
                    'id' => $message->id,
                    'author_name' => $message->author?->name ?? 'Support',
                    'author_role' => $message->user_id === $ticket->created_by_user_id ? 'customer' : 'staff',
                    'body' => $message->body,
                    'created_at' => $message->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'reply_url' => '/laravel/customer/support/tickets/'.$ticket->ticket_reference.'/reply',
            'close_url' => '/laravel/customer/support/tickets/'.$ticket->ticket_reference.'/close',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentCreateForm(User $user): array
    {
        $bookings = Booking::query()
            ->where('customer_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'booking_reference', 'route', 'travel_date']);

        return [
            'ok' => true,
            'categories' => collect(SupportTicketCategory::cases())
                ->map(fn (SupportTicketCategory $category) => [
                    'value' => $category->value,
                    'label' => ucfirst(str_replace('_', ' ', $category->value)),
                ])
                ->values()
                ->all(),
            'bookings' => $bookings->map(fn (Booking $booking) => [
                'id' => $booking->id,
                'booking_reference' => $booking->display_reference,
                'route' => (string) ($booking->route ?? ''),
                'travel_date' => $booking->travel_date?->toDateString(),
            ])->values()->all(),
            'turnstile_required' => false,
            'submit_url' => '/laravel/customer/support/tickets',
        ];
    }
}
