<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingCancellationStatus;
use App\Enums\BookingRefundStatus;
use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Models\BookingRefund;
use App\Models\CommunicationLog;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class OperationalInboxController extends Controller
{
    use RespondsWithBackOfficeJson;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null && ($user->isPlatformAdmin() || $user->isStaff()), 403);

        $agencyScope = ! $user->isPlatformAdmin() ? $user->current_agency_id : null;

        $failedNotifications = CommunicationLog::query()
            ->when($agencyScope, fn ($q) => $q->where('agency_id', $agencyScope))
            ->whereIn('status', ['failed', 'error'])
            ->count();

        $pendingCancellations = BookingCancellationRequest::query()
            ->when($agencyScope, fn ($q) => $q->where('agency_id', $agencyScope))
            ->where('status', BookingCancellationStatus::Requested)
            ->count();

        $pendingRefunds = BookingRefund::query()
            ->when($agencyScope, fn ($q) => $q->where('agency_id', $agencyScope))
            ->where('status', BookingRefundStatus::Pending)
            ->count();

        $assignedBookings = Booking::query()
            ->where('assigned_staff_id', $user->id)
            ->whereNotIn('status', ['cancelled', 'expired', 'failed'])
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'booking_reference', 'pnr', 'status', 'route', 'updated_at']);

        $assignedSupport = SupportTicket::query()
            ->where('assigned_to_user_id', $user->id)
            ->whereNotIn('status', [SupportTicketStatus::Closed, SupportTicketStatus::Resolved])
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'subject', 'status', 'ticket_reference', 'updated_at']);

        $events = [];
        foreach ($assignedBookings as $booking) {
            $events[] = [
                'id' => 'booking-'.$booking->id,
                'type' => 'assigned_booking',
                'title' => 'Assigned booking '.$booking->booking_reference,
                'status' => is_object($booking->status) ? $booking->status->value : (string) $booking->status,
                'unread' => true,
                'deep_link' => '/bookings?selected='.$booking->id,
                'created_at' => $booking->updated_at?->toIso8601String(),
            ];
        }
        foreach ($assignedSupport as $ticket) {
            $events[] = [
                'id' => 'support-'.$ticket->id,
                'type' => 'assigned_support',
                'title' => (string) ($ticket->subject ?: $ticket->ticket_reference),
                'status' => is_object($ticket->status) ? $ticket->status->value : (string) $ticket->status,
                'unread' => true,
                'deep_link' => '/support?ticket='.$ticket->id,
                'created_at' => $ticket->updated_at?->toIso8601String(),
            ];
        }

        $unread = count(array_filter($events, static fn (array $event): bool => ($event['unread'] ?? false) === true));

        return $this->backOfficeJson([
            'ok' => true,
            'fixture_rows' => 0,
            'kpis' => [
                'failed_notifications' => $failedNotifications,
                'pending_cancellations' => $pendingCancellations,
                'pending_refunds' => $pendingRefunds,
                'assigned_bookings' => $assignedBookings->count(),
                'assigned_support' => $assignedSupport->count(),
                'unread' => $unread,
            ],
            'events' => $events,
            'inbox' => $events,
            'assigned_bookings' => $assignedBookings->map(static fn (Booking $booking): array => [
                'id' => (string) $booking->id,
                'booking_reference' => $booking->booking_reference,
                'pnr' => $booking->pnr,
                'status' => is_object($booking->status) ? $booking->status->value : (string) $booking->status,
                'route' => $booking->route,
                'deep_link' => '/bookings?selected='.$booking->id,
            ])->values()->all(),
            'assigned_support' => $assignedSupport->map(static fn (SupportTicket $ticket): array => [
                'id' => (string) $ticket->id,
                'subject' => (string) ($ticket->subject ?: 'Support ticket'),
                'status' => is_object($ticket->status) ? $ticket->status->value : (string) $ticket->status,
                'deep_link' => '/support?ticket='.$ticket->id,
            ])->values()->all(),
            'mark_read_supported' => false,
            'note' => 'Mark-read persistence is not yet backed by a dedicated ops inbox store; unread reflects open assigned work.',
        ]);
    }
}
